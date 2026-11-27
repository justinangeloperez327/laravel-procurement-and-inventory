<?php

namespace App\Domain\Requests;

use App\Domain\Approvals\ApprovalEngine;
use App\Domain\Approvals\ApprovalOutcome;
use App\Domain\Approvals\ApprovalState;
use App\Domain\Budget\BudgetReservations;
use App\Domain\Compliance\ComplianceFlags;
use App\Domain\Identity\Authorizer;
use App\Domain\Planning\AppCoverage;
use App\Domain\Shared\Money;
use App\Domain\Shared\TransitionStatus;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\ApprovalDecision;
use App\Enums\PurchaseRequestStatus as S;
use App\Models\AppItem;
use App\Models\BudgetAllocation;
use App\Models\BudgetReservation;
use App\Models\ComplianceFlag;
use App\Models\FiscalYear;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Purchase request lifecycle (docs/07): draft → submitted → department_review → budget_review →
 * approved, with returned, rejected and cancelled paths.
 *
 * - A PR must be covered by an item of the currently approved APP, within that item's remaining
 *   budget, or carry a documented plan exception that a compliance reviewer must clear (L-01; docs/16
 *   "Unapproved APP expenditure").
 * - Steps follow the agency's purchase_request approval workflow, which must include a budget.certify
 *   step. That step certifies funds and atomically reserves the PR total against an allotment, so no
 *   PR is approved without reserved funds (docs/03 "no financial commitment without funding").
 * - Rejection and cancellation release any reservation.
 */
class PurchaseRequestWorkflow
{
    public const BUDGET_PERMISSION = 'budget.certify';

    /** Statuses whose totals count against an APP item's budget. */
    private const COMMITTED = [S::Submitted, S::DepartmentReview, S::BudgetReview, S::Approved, S::Procurement, S::Fulfilled];

    public function __construct(
        private TransitionStatus $transition,
        private ApprovalEngine $engine,
        private Authorizer $authorizer,
        private BudgetReservations $reservations,
        private ComplianceFlags $flags,
    ) {}

    public function submit(User $actor, PurchaseRequest $pr): PurchaseRequest
    {
        if (! $this->authorizer->allows($actor, 'purchase_request.submit', $pr->department)) {
            throw new WorkflowViolation('You do not hold purchase_request.submit for this department.');
        }

        return DB::transaction(function () use ($actor, $pr) {
            $pr->newQuery()->whereKey($pr->id)->lockForUpdate()->first();
            $pr->refresh();
            if (! $pr->isEditable()) {
                throw new WorkflowViolation("Only draft or returned requests can be submitted; this one is {$pr->status->label()}.");
            }
            if (FiscalYear::find($pr->fiscal_year_id)?->isClosed()) {
                throw new WorkflowViolation('The fiscal year is closed.');
            }
            if (! $pr->items()->exists()) {
                throw new WorkflowViolation('Add at least one item before submitting.');
            }
            $this->assertWorkflowHasBudgetStep($pr);

            $total = (string) DB::table('purchase_request_items')->where('purchase_request_id', $pr->id)->sum('estimated_total');
            $pr->forceFill(['total_amount' => $total])->save();

            $this->assertPlanCoverage($actor, $pr);

            $resubmission = $pr->status === S::Returned;
            ($this->transition)($pr, S::Submitted, $actor, $resubmission ? 'Resubmitted after return' : null);
            ($this->transition)($pr, S::DepartmentReview, $actor);
            $pr->forceFill(['submitted_at' => now()])->save();

            return $pr;
        });
    }

    /** Decides a non-budget step (e.g. department head approval). */
    public function approve(User $actor, PurchaseRequest $pr, ?string $remarks = null): ApprovalState
    {
        return $this->decide($actor, $pr, ApprovalDecision::Approved, $remarks, function (ApprovalState $before) {
            if ($before->nextStep?->permission_code === self::BUDGET_PERMISSION) {
                throw new WorkflowViolation('This step certifies funds: use the budget certification action and choose an allotment.');
            }
        });
    }

    /** The budget step: certifies availability of funds and reserves the PR total against the allotment. */
    public function certifyFunds(User $actor, PurchaseRequest $pr, BudgetAllocation $allocation, ?string $remarks = null, ?string $idempotencyKey = null): ApprovalState
    {
        return $this->decide($actor, $pr, ApprovalDecision::Approved, $remarks, function (ApprovalState $before) use ($pr) {
            if ($before->nextStep?->permission_code !== self::BUDGET_PERMISSION) {
                throw new WorkflowViolation('This request is not at the budget certification step.');
            }
            $blocking = ComplianceFlag::query()
                ->where('subject_type', $pr->getMorphClass())->where('subject_id', $pr->id)
                ->where('severity', 'blocking')->whereIn('status', ComplianceFlags::OPEN_STATUSES)->exists();
            if ($blocking) {
                throw new WorkflowViolation('A blocking compliance flag on this request must be cleared before funds are certified.');
            }
        }, function () use ($actor, $pr, $allocation, $idempotencyKey) {
            $this->reservations->reserve($actor, $allocation, $pr, $idempotencyKey ?? (string) Str::uuid());
        });
    }

    public function returnForCorrection(User $actor, PurchaseRequest $pr, string $remarks): ApprovalState
    {
        return $this->decide($actor, $pr, ApprovalDecision::Returned, $remarks);
    }

    public function reject(User $actor, PurchaseRequest $pr, string $remarks): ApprovalState
    {
        return $this->decide($actor, $pr, ApprovalDecision::Rejected, $remarks);
    }

    /** The requester (or a preparer of the department) withdraws the request before procurement starts. */
    public function cancel(User $actor, PurchaseRequest $pr, string $reason): PurchaseRequest
    {
        $mayCancel = $actor->id === $pr->requested_by || $this->authorizer->allows($actor, 'purchase_request.create', $pr->department);
        if (! $mayCancel) {
            throw new WorkflowViolation('Only the requester or a preparer of the department may cancel this request.');
        }
        if (! in_array($pr->status, [S::Draft, S::Submitted, S::DepartmentReview, S::BudgetReview, S::Returned, S::Approved], true)) {
            throw new WorkflowViolation("A request in {$pr->status->label()} cannot be cancelled here.");
        }

        return DB::transaction(function () use ($actor, $pr, $reason) {
            ($this->transition)($pr, S::Cancelled, $actor, $reason);
            $pr->forceFill(['cancellation_reason' => $reason])->save();
            $this->releaseReservations($actor, $pr, "Purchase request cancelled: {$reason}");

            return $pr;
        });
    }

    /**
     * @param  (callable(ApprovalState): void)|null  $before  Checks before the decision.
     * @param  (callable(): void)|null  $effect  Side effect in the same transaction (e.g. reservation).
     */
    private function decide(User $actor, PurchaseRequest $pr, ApprovalDecision $decision, ?string $remarks, ?callable $before = null, ?callable $effect = null): ApprovalState
    {
        return DB::transaction(function () use ($actor, $pr, $decision, $remarks, $before, $effect) {
            $pr->newQuery()->whereKey($pr->id)->lockForUpdate()->first();
            $pr->refresh();
            if (! in_array($pr->status, [S::DepartmentReview, S::BudgetReview], true)) {
                throw new WorkflowViolation("The request is not under review (it is {$pr->status->label()}).");
            }

            if ($before) {
                $before($this->engine->state($pr));
            }
            $state = $this->engine->act($actor, $pr, $decision, $remarks);
            if ($effect) {
                $effect();
            }

            match ($state->outcome) {
                ApprovalOutcome::Returned => $this->toReturned($actor, $pr, $remarks),
                ApprovalOutcome::Rejected => $this->toRejected($actor, $pr, $remarks),
                ApprovalOutcome::Approved => $this->toApproved($actor, $pr),
                ApprovalOutcome::Pending => $this->syncReviewStage($actor, $pr, $state),
            };

            return $state;
        });
    }

    private function syncReviewStage(User $actor, PurchaseRequest $pr, ApprovalState $state): void
    {
        $atBudget = $state->nextStep?->permission_code === self::BUDGET_PERMISSION || $pr->activeReservation() !== null;
        if ($atBudget && $pr->status === S::DepartmentReview) {
            ($this->transition)($pr, S::BudgetReview, $actor);
        }
    }

    private function toApproved(User $actor, PurchaseRequest $pr): void
    {
        if ($pr->activeReservation() === null) {
            throw new WorkflowViolation('Funds must be certified and reserved before the request can be approved.');
        }
        if ($pr->status === S::DepartmentReview) {
            ($this->transition)($pr, S::BudgetReview, $actor);
        }
        ($this->transition)($pr, S::Approved, $actor);
        $pr->forceFill(['approved_by' => $actor->id, 'approved_at' => now()])->save();
    }

    private function toReturned(User $actor, PurchaseRequest $pr, ?string $remarks): void
    {
        $this->releaseReservations($actor, $pr, 'Purchase request returned for correction');
        ($this->transition)($pr, S::Returned, $actor, $remarks);
    }

    private function toRejected(User $actor, PurchaseRequest $pr, ?string $remarks): void
    {
        $this->releaseReservations($actor, $pr, 'Purchase request rejected');
        ($this->transition)($pr, S::Rejected, $actor, $remarks);
    }

    private function releaseReservations(User $actor, PurchaseRequest $pr, string $reason): void
    {
        $pr->reservations()->where('status', BudgetReservation::STATUS_ACTIVE)->get()
            ->each(fn (BudgetReservation $r) => $this->reservations->release($actor, $r, $reason));
    }

    private function assertWorkflowHasBudgetStep(PurchaseRequest $pr): void
    {
        $workflow = $this->engine->workflowFor($pr);
        if (! $workflow->steps->contains(fn ($s) => $s->is_required && $s->permission_code === self::BUDGET_PERMISSION)) {
            throw new WorkflowViolation("The {$workflow->name} workflow has no required budget certification step. Ask an administrator to add one.");
        }
    }

    private function assertPlanCoverage(User $actor, PurchaseRequest $pr): void
    {
        if ($pr->app_item_id === null) {
            if (blank($pr->plan_exception_basis) || blank($pr->plan_exception_reason)) {
                throw new WorkflowViolation('Link the request to an item of the approved APP, or record the legal basis and reason for a plan exception.');
            }
            $this->flags->raise($pr->agency_id, $pr, 'unplanned_procurement', 'blocking',
                "{$pr->reference_no} is not covered by the approved APP. Basis: {$pr->plan_exception_basis}. Reason: {$pr->plan_exception_reason}", $actor);

            return;
        }

        $item = AppItem::with('appVersion.app')->findOrFail($pr->app_item_id);
        $app = $item->appVersion->app;
        if (! AppCoverage::isUsable($item) || $app->agency_id !== $pr->agency_id || $app->fiscal_year_id !== $pr->fiscal_year_id) {
            throw new WorkflowViolation("APP item {$item->code} is not part of the currently approved APP for this fiscal year.");
        }

        // Serialize submissions against the same APP item so they cannot jointly exceed its budget.
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["app_item:{$item->id}"]);
        $committed = (string) PurchaseRequest::query()
            ->where('app_item_id', $item->id)->whereKeyNot($pr->id)
            ->whereIn('status', self::COMMITTED)
            ->sum('total_amount');
        $remaining = Money::toCents((string) $item->estimated_budget) - Money::toCents($committed);
        if (Money::toCents((string) $pr->total_amount) > $remaining) {
            throw new WorkflowViolation(sprintf('The request (%s) exceeds what remains of APP item %s (%s). Revise the APP or reduce the request.',
                Money::format((string) $pr->total_amount), $item->code, Money::format(Money::fromCents($remaining))));
        }
    }
}
