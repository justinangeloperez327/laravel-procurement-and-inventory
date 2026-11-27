<?php

namespace App\Domain\Bidding;

use App\Domain\Compliance\ComplianceFlags;
use App\Domain\Shared\ReferenceNumbers;
use App\Domain\Shared\TransitionStatus;
use App\Domain\Shared\WorkflowViolation;
use App\Domain\Suppliers\SupplierEligibility;
use App\Enums\ProcurementMode;
use App\Enums\ProcurementProjectStatus;
use App\Models\AuditLog;
use App\Models\Bidding;
use App\Models\ProcurementLot;
use App\Models\ProcurementProject;
use App\Models\Quotation;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Requests for quotation (alternative modes such as small value procurement). Quotations are recorded
 * as received with the server time; quotations after the deadline are recorded as late and never
 * evaluated. Only eligible suppliers' quotations can be recorded (phase 5).
 */
class Solicitations
{
    public function __construct(
        private ReferenceNumbers $numbers,
        private TransitionStatus $transition,
        private SupplierEligibility $eligibility,
        private ComplianceFlags $flags,
    ) {}

    public static function issuedFor(ProcurementProject $project): bool
    {
        return Rfq::where('procurement_project_id', $project->id)->whereIn('status', ['issued', 'closed'])->exists()
            || Bidding::where('procurement_project_id', $project->id)->whereIn('status', ['open', 'closed', 'evaluated'])->exists();
    }

    public function issueRfq(User $actor, ProcurementProject $project, string $deadline, ?int $minimumSuppliers = 3, ?ProcurementLot $lot = null): Rfq
    {
        BiddingGuard::require($actor, 'rfq.manage', $project);
        BiddingGuard::requireStatus($project, [ProcurementProjectStatus::Solicitation]);
        if ($project->procurementMode?->code === ProcurementMode::CompetitiveBidding) {
            throw new WorkflowViolation('This project is for competitive bidding; open a bidding instead of an RFQ.');
        }
        EvaluationCriteria::assertPublished($project);
        if (Carbon::parse($deadline)->isPast()) {
            throw new WorkflowViolation('The submission deadline must be in the future.');
        }

        return DB::transaction(function () use ($actor, $project, $deadline, $minimumSuppliers, $lot) {
            $this->assertNoActiveSolicitation($project, $lot);

            $rfq = Rfq::create([
                'procurement_project_id' => $project->id,
                'procurement_lot_id' => $lot?->id,
                'reference_no' => $this->numbers->next($project->agency_id, 'RFQ', (int) now()->format('Y')),
                'issued_at' => now(),
                'submission_deadline' => $deadline,
                'status' => 'issued',
                'minimum_suppliers' => $minimumSuppliers,
                'issued_by' => $actor->id,
            ]);
            AuditLog::record('rfq.issued', $rfq, null, ['deadline' => $rfq->submission_deadline->toIso8601String()]);

            return $rfq;
        });
    }

    /** @param  array<int, string>  $unitPrices  Unit price per procurement project line id. */
    public function recordQuotation(User $actor, Rfq $rfq, Supplier $supplier, array $unitPrices, ?string $validityUntil = null): Quotation
    {
        $project = $rfq->procurementProject;
        BiddingGuard::require($actor, 'rfq.manage', $project);
        if ($rfq->status !== 'issued') {
            throw new WorkflowViolation("The RFQ is {$rfq->status}.");
        }
        if ($supplier->agency_id !== $project->agency_id) {
            throw new WorkflowViolation('The supplier is registered with another agency.');
        }
        $this->eligibility->assert($supplier);

        return DB::transaction(function () use ($actor, $rfq, $supplier, $unitPrices, $validityUntil) {
            $receivedAt = now();
            $late = $receivedAt->gt($rfq->submission_deadline);
            $lines = $rfq->procurementProject->items()
                ->when($rfq->procurement_lot_id, fn ($q) => $q->where('procurement_lot_id', $rfq->procurement_lot_id))
                ->get();
            if (! $late) {
                foreach ($lines as $line) {
                    if (! isset($unitPrices[$line->id]) || ! is_numeric($unitPrices[$line->id]) || (float) $unitPrices[$line->id] < 0) {
                        throw new WorkflowViolation('Give a unit price for every line of the RFQ.');
                    }
                }
            }

            $quotation = Quotation::create([
                'rfq_id' => $rfq->id,
                'supplier_id' => $supplier->id,
                'received_at' => $receivedAt,
                'total_amount' => 0,
                'validity_until' => $validityUntil,
                'status' => $late ? 'late' : 'received',
                'is_late' => $late,
                'recorded_by' => $actor->id,
            ]);
            if (! $late) {
                foreach ($lines as $line) {
                    $quotation->items()->create([
                        'procurement_project_item_id' => $line->id,
                        'unit_price' => $unitPrices[$line->id],
                        'quantity' => $line->quantity,
                    ]);
                }
                DB::update('UPDATE quotations SET total_amount = (SELECT COALESCE(SUM(total), 0) FROM quotation_items WHERE quotation_id = quotations.id) WHERE id = ?', [$quotation->id]);
            }
            AuditLog::record('quotation.received', $quotation, null, ['late' => $late]);

            return $quotation->fresh();
        });
    }

    /** Closes the RFQ after its deadline and moves the project to evaluation. */
    public function closeRfq(User $actor, Rfq $rfq): Rfq
    {
        $project = $rfq->procurementProject;
        BiddingGuard::require($actor, 'rfq.manage', $project);
        if ($rfq->status !== 'issued') {
            throw new WorkflowViolation("The RFQ is {$rfq->status}.");
        }
        if (now()->lt($rfq->submission_deadline)) {
            throw new WorkflowViolation('The RFQ cannot be closed before its deadline.');
        }

        return DB::transaction(function () use ($actor, $rfq, $project) {
            $rfq->forceFill(['status' => 'closed', 'closed_at' => now()])->save();
            $onTime = $rfq->quotations()->where('is_late', false)->count();
            if ($rfq->minimum_suppliers && $onTime < $rfq->minimum_suppliers) {
                $this->flags->raise($project->agency_id, $project, 'insufficient_quotations', 'warning',
                    "{$rfq->reference_no} received {$onTime} on-time quotations; at least {$rfq->minimum_suppliers} were sought.", $actor);
            }
            self::enterEvaluation($this->transition, $actor, $project);

            return $rfq;
        });
    }

    public static function enterEvaluation(TransitionStatus $transition, User $actor, ProcurementProject $project): void
    {
        $project->refresh();
        if ($project->status === ProcurementProjectStatus::Solicitation) {
            $transition($project, ProcurementProjectStatus::Receipt, $actor);
        }
        if ($project->status === ProcurementProjectStatus::Receipt) {
            $transition($project, ProcurementProjectStatus::Evaluation, $actor);
        }
    }

    public function assertNoActiveSolicitation(ProcurementProject $project, ?ProcurementLot $lot): void
    {
        $scope = fn ($q) => $lot ? $q->where('procurement_lot_id', $lot->id) : $q->whereNull('procurement_lot_id');
        $active = Rfq::where('procurement_project_id', $project->id)->where($scope)->whereIn('status', ['draft', 'issued', 'closed'])->exists()
            || Bidding::where('procurement_project_id', $project->id)->where($scope)->whereIn('status', ['draft', 'open', 'closed', 'evaluated'])->exists();
        if ($active) {
            throw new WorkflowViolation('This project (or lot) already has an active solicitation.');
        }
    }
}
