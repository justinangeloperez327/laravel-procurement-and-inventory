<?php

namespace App\Domain\Bidding;

use App\Domain\Documents\DocumentStore;
use App\Domain\Procurement\ProjectSchedule;
use App\Domain\Shared\Money;
use App\Domain\Shared\ReferenceNumbers;
use App\Domain\Shared\TransitionStatus;
use App\Domain\Shared\WorkflowViolation;
use App\Domain\Suppliers\SupplierEligibility;
use App\Enums\DocumentClassification;
use App\Enums\ProcurementMode;
use App\Enums\ProcurementProjectStatus;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Bidding;
use App\Models\BidDocument;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\ProcurementDeadline;
use App\Models\ProcurementLot;
use App\Models\ProcurementProject;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Competitive bidding (L-07): sealed bids with immutable receipt timestamps, late-bid handling, and
 * authorized opening at a quorate BAC session.
 *
 * - The receipt time is the server's clock when the secretariat records the bid; it can never change.
 * - Bids received after the submission deadline (the latest extension counts) are recorded as late and
 *   returned unopened (docs/16 "Late bid").
 * - Bid envelopes are stored as sealed documents. Nobody can read them, and no amount exists in the
 *   system, until the BAC opens the bids at or after the opening time (docs/16 "Early confidential
 *   bid read → deny and record").
 */
class CompetitiveBidding
{
    public const ENVELOPES = ['eligibility_technical' => 'Eligibility and technical envelope', 'financial' => 'Financial envelope'];

    public function __construct(
        private ReferenceNumbers $numbers,
        private TransitionStatus $transition,
        private SupplierEligibility $eligibility,
        private DocumentStore $documents,
        private Solicitations $solicitations,
    ) {}

    public function open(User $actor, ProcurementProject $project, string $submissionDeadline, string $openingAt, ?string $documentsFee = null, ?ProcurementLot $lot = null): Bidding
    {
        BiddingGuard::require($actor, 'rfq.manage', $project);
        BiddingGuard::requireStatus($project, [ProcurementProjectStatus::Solicitation]);
        if ($project->procurementMode?->code !== ProcurementMode::CompetitiveBidding) {
            throw new WorkflowViolation('Only projects approved for competitive bidding can be bid out.');
        }
        EvaluationCriteria::assertPublished($project);
        if (Carbon::parse($submissionDeadline)->isPast() || Carbon::parse($openingAt)->lt(Carbon::parse($submissionDeadline))) {
            throw new WorkflowViolation('The deadline must be in the future and opening must not precede it.');
        }

        return DB::transaction(function () use ($actor, $project, $submissionDeadline, $openingAt, $documentsFee, $lot) {
            $this->solicitations->assertNoActiveSolicitation($project, $lot);
            $bidding = Bidding::create([
                'procurement_project_id' => $project->id,
                'procurement_lot_id' => $lot?->id,
                'reference_no' => $this->numbers->next($project->agency_id, 'BID', (int) now()->format('Y')),
                'bid_submission_deadline' => $submissionDeadline,
                'bid_opening_at' => $openingAt,
                'status' => 'open',
                'bidding_documents_fee' => $documentsFee,
                'issued_by' => $actor->id,
            ]);
            $schedule = app(ProjectSchedule::class);
            if (! ProjectSchedule::currentDeadlines($project)->contains('deadline_type', 'bid_submission')) {
                $schedule->setDeadline($actor, $project, 'bid_submission', $submissionDeadline, "Bidding {$bidding->reference_no}");
            }
            if (! ProjectSchedule::currentDeadlines($project)->contains('deadline_type', 'bid_opening')) {
                $schedule->setDeadline($actor, $project, 'bid_opening', $openingAt, "Bidding {$bidding->reference_no}");
            }
            AuditLog::record('bidding.opened', $bidding, null, ['deadline' => $submissionDeadline, 'opening' => $openingAt]);

            return $bidding;
        });
    }

    /** The effective deadline: the latest extension of the project's bid_submission deadline, else the original. */
    public static function effectiveDeadline(Bidding $bidding, string $type = 'bid_submission'): Carbon
    {
        $current = ProjectSchedule::currentDeadlines($bidding->procurementProject)->firstWhere('deadline_type', $type);
        $original = $type === 'bid_submission' ? $bidding->bid_submission_deadline : $bidding->bid_opening_at;

        return $current instanceof ProcurementDeadline ? $current->due_at->copy() : $original->copy();
    }

    /**
     * Records receipt of a sealed bid. Envelope files are stored as sealed documents.
     *
     * @param  array<string, UploadedFile|File>  $envelopes  keyed by envelope type
     */
    public function receive(User $actor, Bidding $bidding, Supplier $supplier, array $envelopes = []): Bid
    {
        $project = $bidding->procurementProject;
        BiddingGuard::require($actor, 'rfq.manage', $project);
        if ($bidding->status !== 'open') {
            throw new WorkflowViolation("The bidding is {$bidding->status}.");
        }
        if ($supplier->agency_id !== $project->agency_id) {
            throw new WorkflowViolation('The supplier is registered with another agency.');
        }

        return DB::transaction(function () use ($actor, $bidding, $supplier, $envelopes, $project) {
            $receivedAt = now();
            $late = $receivedAt->gt(self::effectiveDeadline($bidding));
            if (! $late) {
                $this->eligibility->assert($supplier);
            }
            $existing = Bid::where('bidding_id', $bidding->id)->where('supplier_id', $supplier->id)->whereNull('withdrawn_at')->exists();
            if ($existing) {
                throw new WorkflowViolation("{$supplier->name} already has a bid in this bidding. Withdraw it first to resubmit.");
            }

            $bid = Bid::create([
                'bidding_id' => $bidding->id,
                'supplier_id' => $supplier->id,
                'procurement_lot_id' => $bidding->procurement_lot_id,
                'submitted_at' => $receivedAt,
                'receipt_reference' => $this->numbers->next($project->agency_id, 'RCPT', (int) $receivedAt->format('Y')),
                'bid_amount' => null,
                'is_late' => $late,
                'status' => $late ? 'returned_unopened' : 'submitted',
                'received_by' => $actor->id,
            ]);

            foreach ($envelopes as $envelope => $file) {
                if (! array_key_exists($envelope, self::ENVELOPES)) {
                    throw new WorkflowViolation("Unknown envelope {$envelope}.");
                }
                $document = Document::create([
                    'agency_id' => $project->agency_id,
                    'documentable_type' => $bid->getMorphClass(),
                    'documentable_id' => $bid->id,
                    'document_type' => "bid.{$envelope}",
                    'title' => self::ENVELOPES[$envelope]." — {$supplier->name} — {$bid->receipt_reference}",
                    'classification' => DocumentClassification::SealedBid,
                    'created_by' => $actor->id,
                ]);
                $this->documents->addVersion($document, $file, $actor);
                BidDocument::create(['bid_id' => $bid->id, 'document_id' => $document->id, 'envelope' => $envelope, 'is_sealed' => true]);
            }

            AuditLog::record($late ? 'bid.received_late' : 'bid.received', $bid, null, [
                'receipt' => $bid->receipt_reference, 'submitted_at' => $receivedAt->toIso8601String(), 'late' => $late,
            ]);

            return $bid;
        });
    }

    /** A bidder may withdraw before the deadline; the receipt record stays. */
    public function withdraw(User $actor, Bid $bid, string $reason): Bid
    {
        BiddingGuard::require($actor, 'rfq.manage', $bid->bidding->procurementProject);
        if ($bid->withdrawn_at !== null || $bid->opened_at !== null) {
            throw new WorkflowViolation('Only an unopened bid can be withdrawn.');
        }
        if (now()->gt(self::effectiveDeadline($bid->bidding))) {
            throw new WorkflowViolation('Bids cannot be withdrawn after the submission deadline.');
        }
        $bid->forceFill(['withdrawn_at' => now(), 'status' => 'withdrawn'])->save();
        AuditLog::record('bid.withdrawn', $bid, null, null, ['reason' => $reason]);

        return $bid;
    }

    /**
     * Opens the bids at a held, quorate BAC meeting for this project, at or after the opening time.
     * Each on-time bid's amount, as read out from its financial envelope, is recorded once.
     *
     * @param  array<int, string>  $amounts  bid id => amount read at opening
     */
    public function openBids(User $actor, Bidding $bidding, Meeting $meeting, array $amounts): Bidding
    {
        $project = $bidding->procurementProject;
        BiddingGuard::require($actor, 'bid.open', $project);
        if ($bidding->status !== 'open') {
            throw new WorkflowViolation("The bidding is {$bidding->status}.");
        }
        if (now()->lt(self::effectiveDeadline($bidding, 'bid_opening'))) {
            AuditLog::record('bid.early_opening_denied', $bidding, null, null, ['actor' => $actor->id]);
            throw new WorkflowViolation('Bids cannot be opened before the scheduled opening time.');
        }
        if ($meeting->bac_committee_id === null || $meeting->procurement_project_id !== $project->id || ! $meeting->quorum_met) {
            throw new WorkflowViolation('Bids are opened at a held BAC meeting for this project with quorum.');
        }
        if (! $meeting->attendees()->where('user_id', $actor->id)->where('present', true)->exists()) {
            throw new WorkflowViolation('Only a BAC member present at the opening may record it.');
        }

        return DB::transaction(function () use ($actor, $bidding, $meeting, $amounts, $project) {
            $bids = $bidding->bids()->where('is_late', false)->whereNull('withdrawn_at')->lockForUpdate()->get();
            foreach ($bids as $bid) {
                $amount = $amounts[$bid->id] ?? null;
                if ($amount === null || ! is_numeric($amount) || Money::toCents((string) $amount) <= 0) {
                    throw new WorkflowViolation("Record the amount read from bid {$bid->receipt_reference}.");
                }
            }

            $now = now();
            foreach ($bids as $bid) {
                $bid->forceFill(['opened_at' => $now, 'opened_by' => $actor->id, 'status' => 'opened'])->save();
                $bid->forceFill(['bid_amount' => Money::fromCents(Money::toCents((string) $amounts[$bid->id]))])->save();
                Document::whereIn('id', BidDocument::where('bid_id', $bid->id)->pluck('document_id'))->update(['unsealed_at' => $now]);
                BidDocument::where('bid_id', $bid->id)->update(['is_sealed' => false]);
            }
            $bidding->forceFill(['status' => 'closed', 'opened_at' => $now, 'opening_meeting_id' => $meeting->id])->save();
            AuditLog::record('bidding.bids_opened', $bidding, null, [
                'bids' => $bids->mapWithKeys(fn (Bid $b) => [$b->receipt_reference => $b->fresh()->bid_amount])->all(),
            ]);
            Solicitations::enterEvaluation($this->transition, $actor, $project);

            return $bidding;
        });
    }
}
