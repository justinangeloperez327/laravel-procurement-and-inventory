<?php

namespace App\Domain\Procurement;

use App\Domain\Compliance\ConflictDeclarations;
use App\Domain\Regulatory\RuleBook;
use App\Domain\Shared\TransitionStatus;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\ApprovalDecision;
use App\Enums\ProcurementMode;
use App\Enums\ProcurementProjectStatus;
use App\Models\AuditLog;
use App\Models\BacResolution;
use App\Models\ProcurementJustification;
use App\Models\ProcurementModeDefinition;
use App\Models\ProcurementProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Choosing the procurement mode (docs/07 step 3; L-04):
 *   1. Procurement staff propose a mode with a justification and evidence; it is screened against the
 *      approved rules and refused if a blocking rule fails (docs/16 "Ineligible procurement mode").
 *   2. A BAC member reviews it (endorse, return or reject). Endorsing an alternative mode needs a BAC
 *      resolution recommending it, adopted at a meeting with quorum.
 *   3. Alternative modes then need HoPE approval; competitive bidding, the default method, does not.
 * Reviewers must have declared no conflict of interest for the project. The project moves to
 * solicitation once the mode is final.
 */
class ModeSelection
{
    public function __construct(
        private ModeScreening $screening,
        private SplittingCheck $splitting,
        private TransitionStatus $transition,
    ) {}

    /** @param  array<string, string|null>  $evidence */
    public function propose(User $actor, ProcurementProject $project, ProcurementMode $mode, string $justification, array $evidence = []): ProcurementJustification
    {
        ProcurementGuard::require($actor, 'procurement.manage', $project);
        if (blank($justification)) {
            throw new WorkflowViolation('A justification is required.');
        }

        return DB::transaction(function () use ($actor, $project, $mode, $justification, $evidence) {
            $project->newQuery()->whereKey($project->id)->lockForUpdate()->first();
            $project->refresh();
            if ($project->status !== ProcurementProjectStatus::Proposed) {
                throw new WorkflowViolation("A mode can be proposed only while the project is proposed (it is {$project->status->label()}).");
            }
            if (! $project->items()->exists()) {
                throw new WorkflowViolation('The project has no lines.');
            }

            $result = $this->screening->screen($project, $mode, $evidence);
            if (! $result->eligible()) {
                throw new WorkflowViolation("{$mode->label()} is not allowed for {$project->reference_no}: "
                    .implode(' ', array_column($result->blockingFailures(), 'message')));
            }

            $definition = ProcurementModeDefinition::where('code', $mode)->firstOrFail();
            $record = ProcurementJustification::create([
                'procurement_project_id' => $project->id,
                'procurement_mode_id' => $definition->id,
                'regulatory_rule_version_id' => $this->primaryRuleVersionId($result),
                'justification' => $justification,
                'evidence' => $evidence ?: null,
                'prepared_by' => $actor->id,
                'status' => 'proposed',
                'screening_result' => $result->toArray(),
            ]);
            ($this->transition)($project, ProcurementProjectStatus::ModeReview, $actor);
            $this->splitting->check($project, $result);

            return $record;
        });
    }

    public function bacReview(User $actor, ProcurementJustification $proposal, ApprovalDecision $decision, ?string $remarks = null, ?BacResolution $resolution = null): ProcurementJustification
    {
        $project = $proposal->procurementProject;
        ProcurementGuard::require($actor, 'procurement_mode.review', $project);
        if (! in_array($decision, [ApprovalDecision::Endorsed, ApprovalDecision::Returned, ApprovalDecision::Rejected], true)) {
            throw new WorkflowViolation('The BAC endorses, returns or rejects a proposed mode.');
        }

        return DB::transaction(function () use ($actor, $proposal, $project, $decision, $remarks, $resolution) {
            $locked = $this->lock($proposal, 'proposed');
            $this->assertIndependent($actor, $locked, $project);
            $mode = $locked->procurementMode->code;

            if ($decision !== ApprovalDecision::Endorsed) {
                return $this->sendBack($actor, $locked, $project, $decision, $remarks, ['reviewed_by' => $actor->id, 'reviewed_at' => now(), 'decision' => $decision, 'decision_remarks' => $remarks]);
            }

            if ($mode !== ProcurementMode::CompetitiveBidding) {
                if ($resolution === null || $resolution->procurement_project_id !== $project->id || $resolution->resolution_type !== 'recommend_mode') {
                    throw new WorkflowViolation('Endorsing an alternative mode needs a BAC resolution recommending it for this project.');
                }
            }
            $rescreen = $this->screening->screen($project, $mode, $locked->evidence ?? []);
            if (! $rescreen->eligible()) {
                throw new WorkflowViolation('The mode no longer passes screening: '.implode(' ', array_column($rescreen->blockingFailures(), 'message')));
            }

            $locked->forceFill([
                'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'decision' => ApprovalDecision::Endorsed,
                'decision_remarks' => $remarks, 'bac_resolution_id' => $resolution?->id, 'status' => 'endorsed',
                'screening_result' => $rescreen->toArray(),
            ])->save();
            AuditLog::record('procurement_mode.endorsed', $locked, null, ['mode' => $mode->value]);

            return $mode === ProcurementMode::CompetitiveBidding ? $this->finalize($actor, $locked, $project) : $locked;
        });
    }

    public function hopeDecision(User $actor, ProcurementJustification $proposal, ApprovalDecision $decision, ?string $remarks = null): ProcurementJustification
    {
        $project = $proposal->procurementProject;
        ProcurementGuard::require($actor, 'procurement_mode.approve', $project);
        if (! in_array($decision, [ApprovalDecision::Approved, ApprovalDecision::Returned, ApprovalDecision::Rejected], true)) {
            throw new WorkflowViolation('The HoPE approves, returns or rejects an endorsed mode.');
        }

        return DB::transaction(function () use ($actor, $proposal, $project, $decision, $remarks) {
            $locked = $this->lock($proposal, 'endorsed');
            $this->assertIndependent($actor, $locked, $project);
            if ($actor->id === $locked->reviewed_by) {
                throw new WorkflowViolation('The BAC reviewer cannot also approve the mode.');
            }

            if ($decision !== ApprovalDecision::Approved) {
                return $this->sendBack($actor, $locked, $project, $decision, $remarks, []);
            }

            AuditLog::record('procurement_mode.approved', $locked, null, ['mode' => $locked->procurementMode->code->value], array_filter(['remarks' => $remarks]));

            return $this->finalize($actor, $locked, $project);
        });
    }

    private function finalize(User $actor, ProcurementJustification $proposal, ProcurementProject $project): ProcurementJustification
    {
        $proposal->forceFill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()])->save();
        $project->forceFill(['procurement_mode_id' => $proposal->procurement_mode_id])->save();
        ($this->transition)($project, ProcurementProjectStatus::Solicitation, $actor);

        return $proposal;
    }

    private function sendBack(User $actor, ProcurementJustification $proposal, ProcurementProject $project, ApprovalDecision $decision, ?string $remarks, array $fields): ProcurementJustification
    {
        if (blank($remarks)) {
            throw new WorkflowViolation('Remarks are required when returning or rejecting.');
        }
        $status = $decision === ApprovalDecision::Rejected ? 'rejected' : 'returned';
        $proposal->forceFill([...$fields, 'status' => $status])->save();
        AuditLog::record("procurement_mode.{$status}", $proposal, null, null, ['remarks' => $remarks]);
        ($this->transition)($project, ProcurementProjectStatus::Proposed, $actor, "Mode {$status}: {$remarks}");

        return $proposal;
    }

    private function lock(ProcurementJustification $proposal, string $expected): ProcurementJustification
    {
        $locked = ProcurementJustification::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
        if ($locked->status !== $expected) {
            throw new WorkflowViolation("This proposal is {$locked->status}, not {$expected}.");
        }

        return $locked;
    }

    private function assertIndependent(User $actor, ProcurementJustification $proposal, ProcurementProject $project): void
    {
        if ($actor->id === $proposal->prepared_by) {
            throw new WorkflowViolation('The preparer of a mode proposal cannot decide on it.');
        }
        if (! ConflictDeclarations::hasDeclared($actor, $project)) {
            throw new WorkflowViolation("Declare whether you have a conflict of interest in {$project->reference_no} first.");
        }
        if (ConflictDeclarations::mustRecuse($actor, $project)) {
            throw new WorkflowViolation('You declared a conflict of interest in this project and must recuse.');
        }
    }

    private function primaryRuleVersionId(ScreeningResult $result): ?int
    {
        foreach ($result->checks as $check) {
            if ($check['version'] !== null) {
                return app(RuleBook::class)->effective($check['rule_code'], $result->governingDate)?->id;
            }
        }

        return null;
    }
}
