<?php

namespace App\Domain\Bidding;

use App\Domain\Shared\TransitionStatus;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\ProcurementProjectStatus;
use App\Models\AuditLog;
use App\Models\BacResolution;
use App\Models\Bidding;
use App\Models\ProcurementProject;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** BAC recommendation of award or declaration of failure, each by resolution (L-06, L-08). */
class Recommendation
{
    public function __construct(private TransitionStatus $transition) {}

    public function recommendAward(User $actor, ProcurementProject $project, BacResolution $resolution): ProcurementProject
    {
        BiddingGuard::require($actor, 'bac.recommend', $project);
        BiddingGuard::requireStatus($project, [ProcurementProjectStatus::PostQualification]);
        $this->assertResolution($resolution, $project, 'recommend_award');
        if (! $project->postQualifications()->where('result', 'passed')->exists()) {
            throw new WorkflowViolation('An offer must pass post-qualification before award is recommended.');
        }

        return DB::transaction(function () use ($actor, $project, $resolution) {
            ($this->transition)($project, ProcurementProjectStatus::Recommended, $actor, "BAC {$resolution->resolution_no}");
            AuditLog::record('award.recommended', $project, null, ['resolution' => $resolution->resolution_no]);

            return $project;
        });
    }

    public function declareFailure(User $actor, ProcurementProject $project, BacResolution $resolution): ProcurementProject
    {
        BiddingGuard::require($actor, 'bac.recommend', $project);
        BiddingGuard::requireStatus($project, [ProcurementProjectStatus::Solicitation, ProcurementProjectStatus::Receipt,
            ProcurementProjectStatus::Evaluation, ProcurementProjectStatus::PostQualification, ProcurementProjectStatus::Recommended]);
        $this->assertResolution($resolution, $project, 'declare_failure');

        return DB::transaction(function () use ($actor, $project, $resolution) {
            Rfq::where('procurement_project_id', $project->id)->whereIn('status', ['issued', 'closed'])->update(['status' => 'failed']);
            Bidding::where('procurement_project_id', $project->id)->whereIn('status', ['open', 'closed', 'evaluated'])->update(['status' => 'failed']);
            ($this->transition)($project, ProcurementProjectStatus::Failed, $actor, "BAC {$resolution->resolution_no}: {$resolution->title}");

            return $project;
        });
    }

    private function assertResolution(BacResolution $resolution, ProcurementProject $project, string $type): void
    {
        if ($resolution->procurement_project_id !== $project->id || $resolution->resolution_type !== $type) {
            throw new WorkflowViolation('Attach the BAC resolution of the right type for this project.');
        }
    }
}
