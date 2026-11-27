<?php

namespace App\Domain\Approvals;

use App\Domain\Identity\Authorizer;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\ApprovalDecision;
use App\Models\ApprovalAction;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Delegation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Routes Approvable records through the agency's configured approval workflow.
 *
 * Steps are taken in sequence. Each step requires a scoped permission, resolved by the Authorizer,
 * so delegated authority works and is recorded. A return restarts the round; a rejection is final.
 * The engine records decisions only. The calling domain action moves the record's status.
 */
class ApprovalEngine
{
    public function __construct(private Authorizer $authorizer) {}

    public function workflowFor(Approvable&Model $subject, ?CarbonInterface $on = null): ApprovalWorkflow
    {
        $on ??= now();

        $workflow = ApprovalWorkflow::query()
            ->where('agency_id', $subject->approvalAgencyId())
            ->where('subject_type', $subject->approvalSubjectType())
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $on))
            ->orderByRaw('effective_from DESC NULLS LAST')
            ->orderByDesc('id')
            ->with(['steps' => fn ($q) => $q->orderBy('sequence')])
            ->first();

        if ($workflow === null) {
            throw new WorkflowViolation("No active approval workflow is configured for {$subject->approvalSubjectType()}.");
        }
        if ($workflow->steps->where('is_required', true)->isEmpty()) {
            throw new WorkflowViolation("The {$workflow->name} workflow has no required steps.");
        }

        return $workflow;
    }

    public function state(Approvable&Model $subject): ApprovalState
    {
        $workflow = $this->workflowFor($subject);
        $actions = ApprovalAction::query()
            ->where('approvable_type', $subject->getMorphClass())
            ->where('approvable_id', $subject->getKey())
            ->orderBy('id')
            ->get();

        $lastReturn = $actions->last(fn (ApprovalAction $a) => $a->decision === ApprovalDecision::Returned);
        $round = $lastReturn ? $actions->filter(fn (ApprovalAction $a) => $a->id > $lastReturn->id)->values() : $actions;

        if ($round->contains(fn (ApprovalAction $a) => $a->decision === ApprovalDecision::Rejected)) {
            return new ApprovalState($workflow, ApprovalOutcome::Rejected, null, $round);
        }
        if ($lastReturn !== null && $round->isEmpty()) {
            return new ApprovalState($workflow, ApprovalOutcome::Returned, $this->nextStep($workflow, $round), $round);
        }

        $next = $this->nextStep($workflow, $round);

        return new ApprovalState($workflow, $next ? ApprovalOutcome::Pending : ApprovalOutcome::Approved, $next, $round);
    }

    public function act(User $actor, Approvable&Model $subject, ApprovalDecision $decision, ?string $remarks = null): ApprovalState
    {
        return DB::transaction(function () use ($actor, $subject, $decision, $remarks) {
            // Serialize decisions on the same record.
            $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();

            $state = $this->state($subject);
            if (! $state->isOpen() || $state->nextStep === null) {
                throw new WorkflowViolation("Approval is already {$state->outcome->value}.");
            }
            if (in_array($decision, [ApprovalDecision::Returned, ApprovalDecision::Rejected], true) && blank($remarks)) {
                throw new WorkflowViolation('Remarks are required when returning or rejecting.');
            }

            $step = $state->nextStep;
            $delegation = $this->authorize($actor, $subject, $step, $state->roundActions);

            ApprovalAction::create([
                'approval_step_id' => $step->id,
                'approvable_type' => $subject->getMorphClass(),
                'approvable_id' => $subject->getKey(),
                'actor_id' => $actor->id,
                'on_behalf_of_id' => $delegation?->delegator_id,
                'delegation_id' => $delegation?->id,
                'decision' => $decision,
                'remarks' => $remarks,
                'acted_at' => now(),
            ]);

            return $this->state($subject);
        });
    }

    /**
     * @param  Collection<int, ApprovalAction>  $round
     * @return Delegation|null The delegation relied on, if the actor acts on delegated authority.
     */
    private function authorize(User $actor, Approvable&Model $subject, ApprovalStep $step, Collection $round): ?Delegation
    {
        $agencyId = $subject->approvalAgencyId();
        if ($actor->agency_id !== $agencyId) {
            throw new WorkflowViolation('Approvers must belong to the same agency as the record.');
        }
        if ($subject->approvalInitiatorId() === $actor->id) {
            throw new WorkflowViolation('The initiator of a record may not approve it.');
        }

        $department = $step->department_scoped ? $subject->approvalDepartment() : null;
        if ($step->department_scoped && $department === null) {
            throw new WorkflowViolation("Step {$step->name} is department-scoped but the record has no department.");
        }

        $direct = $this->authorizer->holdsDirectly($actor, $step->permission_code, $agencyId, $department, now());
        $delegation = $direct ? null : $this->authorizer->delegationUsed($actor, $step->permission_code, $agencyId, $department);
        if (! $direct && $delegation === null) {
            throw new WorkflowViolation("You do not hold {$step->permission_code} for step {$step->name}.");
        }

        // One person (or the principal they act for) may decide only one step per round.
        $principal = $delegation?->delegator_id ?? $actor->id;
        $alreadyActed = $round->contains(fn (ApprovalAction $a) => in_array($a->actor_id, [$actor->id, $principal], true)
            || in_array($a->on_behalf_of_id, [$actor->id, $principal], true));
        if ($alreadyActed) {
            throw new WorkflowViolation('The same person may not decide more than one step of an approval.');
        }

        return $delegation;
    }

    /** @param  Collection<int, ApprovalAction>  $round */
    private function nextStep(ApprovalWorkflow $workflow, Collection $round): ?ApprovalStep
    {
        $done = $round
            ->filter(fn (ApprovalAction $a) => in_array($a->decision, [ApprovalDecision::Approved, ApprovalDecision::Endorsed], true))
            ->pluck('approval_step_id')
            ->all();

        return $workflow->steps
            ->where('is_required', true)
            ->sortBy('sequence')
            ->first(fn (ApprovalStep $s) => ! in_array($s->id, $done, true));
    }
}
