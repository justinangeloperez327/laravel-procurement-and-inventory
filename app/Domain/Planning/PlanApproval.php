<?php

namespace App\Domain\Planning;

use App\Domain\Approvals\ApprovalEngine;
use App\Domain\Approvals\ApprovalOutcome;
use App\Domain\Approvals\ApprovalState;
use App\Domain\Identity\Authorizer;
use App\Domain\Shared\TransitionStatus;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\ApprovalDecision;
use App\Enums\PlanStatus;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Submission and approval of plan documents (docs/07 step 1; L-01). */
class PlanApproval
{
    public function __construct(
        private TransitionStatus $transition,
        private ApprovalEngine $engine,
        private Authorizer $authorizer,
    ) {}

    public function submit(User $actor, PlanDocument&Model $plan): PlanDocument&Model
    {
        if (! $this->authorizer->allows($actor, $plan->submissionPermission(), $plan->approvalDepartment())) {
            throw new WorkflowViolation("You do not hold {$plan->submissionPermission()} for this plan.");
        }

        return DB::transaction(function () use ($actor, $plan) {
            $plan->newQuery()->whereKey($plan->getKey())->lockForUpdate()->first();
            $plan->refresh();

            $this->assertFiscalYearOpen($plan);
            $plan->assertReadyForSubmission();
            $plan->recalculateTotal();

            $reason = $plan->status === PlanStatus::Returned ? 'Resubmitted after return' : null;
            ($this->transition)($plan, PlanStatus::Submitted, $actor, $reason);
            $plan->forceFill(['submitted_at' => now()])->save();

            return $plan;
        });
    }

    public function decide(User $actor, PlanDocument&Model $plan, ApprovalDecision $decision, ?string $remarks = null): ApprovalState
    {
        if ($decision === ApprovalDecision::Rejected) {
            throw new WorkflowViolation('Plans are returned with remarks for correction, not rejected.');
        }

        return DB::transaction(function () use ($actor, $plan, $decision, $remarks) {
            $plan->refresh();
            if ($plan->status !== PlanStatus::Submitted) {
                throw new WorkflowViolation('Only submitted plans can be decided.');
            }

            $state = $this->engine->act($actor, $plan, $decision, $remarks);

            if ($state->outcome === ApprovalOutcome::Returned) {
                ($this->transition)($plan, PlanStatus::Returned, $actor, $remarks);
            } elseif ($state->outcome === ApprovalOutcome::Approved) {
                ($this->transition)($plan, PlanStatus::Approved, $actor);
                $plan->forceFill(['approved_by' => $actor->id, 'approved_at' => now()])->save();
                $plan->afterApproved($actor);
            }

            return $state;
        });
    }

    private function assertFiscalYearOpen(Model $plan): void
    {
        $fiscalYearId = array_key_exists('fiscal_year_id', $plan->getAttributes())
            ? $plan->getAttribute('fiscal_year_id')
            : $plan->app->fiscal_year_id;
        $fiscalYear = FiscalYear::find($fiscalYearId);
        if ($fiscalYear?->isClosed()) {
            throw new WorkflowViolation("Fiscal year {$fiscalYear->year} is closed.");
        }
    }
}
