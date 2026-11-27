<?php

namespace App\Domain\Bidding;

use App\Domain\Shared\WorkflowViolation;
use App\Enums\ProcurementProjectStatus;
use App\Models\AuditLog;
use App\Models\EvaluationCriterion;
use App\Models\ProcurementLot;
use App\Models\ProcurementProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Evaluation criteria are drafted, then published, which locks them. Locked criteria can never change,
 * and new criteria cannot be added once solicitation has been issued (L-08; docs/16 "Evaluation
 * criteria changed improperly → block").
 */
class EvaluationCriteria
{
    public const TYPES = ['pass_fail' => 'Pass / fail', 'rated' => 'Rated (weighted score)'];

    public function add(User $actor, ProcurementProject $project, string $criterion, string $type, ?string $weight = null, ?ProcurementLot $lot = null, ?string $description = null): EvaluationCriterion
    {
        BiddingGuard::require($actor, 'rfq.manage', $project);
        BiddingGuard::requireStatus($project, [ProcurementProjectStatus::Proposed, ProcurementProjectStatus::ModeReview, ProcurementProjectStatus::Solicitation]);
        if (Solicitations::issuedFor($project)) {
            throw new WorkflowViolation('Solicitation has been issued; criteria can no longer be added.');
        }
        if (! array_key_exists($type, self::TYPES)) {
            throw new WorkflowViolation("Unknown criterion type {$type}.");
        }
        if ($type === 'rated' && (! is_numeric($weight) || (float) $weight <= 0)) {
            throw new WorkflowViolation('Rated criteria need a positive weight.');
        }
        if ($lot && $lot->procurement_project_id !== $project->id) {
            throw new WorkflowViolation('The lot belongs to another project.');
        }

        return EvaluationCriterion::create([
            'procurement_project_id' => $project->id,
            'procurement_lot_id' => $lot?->id,
            'sequence' => (int) $project->criteria()->max('sequence') + 1,
            'criterion' => $criterion,
            'description' => $description,
            'criterion_type' => $type,
            'weight' => $type === 'rated' ? $weight : null,
            'created_by' => $actor->id,
        ]);
    }

    /** Publishes and locks every draft criterion of the project. */
    public function publish(User $actor, ProcurementProject $project): int
    {
        BiddingGuard::require($actor, 'rfq.manage', $project);

        return DB::transaction(function () use ($project) {
            $drafts = $project->criteria()->whereNull('locked_at')->lockForUpdate()->get();
            if ($drafts->isEmpty()) {
                throw new WorkflowViolation('There are no draft criteria to publish.');
            }
            $now = now();
            EvaluationCriterion::whereKey($drafts->pluck('id'))->update(['is_published' => true, 'published_at' => $now, 'locked_at' => $now, 'updated_at' => $now]);
            AuditLog::record('evaluation_criteria.published', $project, null, ['criteria' => $drafts->pluck('criterion')->all()]);

            return $drafts->count();
        });
    }

    public static function assertPublished(ProcurementProject $project): void
    {
        if (! $project->criteria()->exists()) {
            throw new WorkflowViolation('Define and publish the evaluation criteria before solicitation.');
        }
        if ($project->criteria()->whereNull('locked_at')->exists()) {
            throw new WorkflowViolation('Publish all evaluation criteria before solicitation.');
        }
    }
}
