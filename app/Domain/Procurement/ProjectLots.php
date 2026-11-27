<?php

namespace App\Domain\Procurement;

use App\Domain\Shared\WorkflowViolation;
use App\Enums\ProcurementProjectStatus;
use App\Models\ProcurementLot;
use App\Models\ProcurementProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Lots can be arranged only while the project is proposed. Each lot's ABC is the sum of its lines. */
class ProjectLots
{
    /** @param  list<int>  $projectItemIds */
    public function create(User $actor, ProcurementProject $project, string $title, array $projectItemIds): ProcurementLot
    {
        ProcurementGuard::require($actor, 'procurement.manage', $project);
        if ($project->status !== ProcurementProjectStatus::Proposed) {
            throw new WorkflowViolation('Lots can only be arranged while the project is proposed.');
        }
        if ($projectItemIds === []) {
            throw new WorkflowViolation('A lot needs at least one line.');
        }

        return DB::transaction(function () use ($project, $title, $projectItemIds) {
            $items = $project->items()->whereKey($projectItemIds)->lockForUpdate()->get();
            if ($items->count() !== count(array_unique($projectItemIds))) {
                throw new WorkflowViolation('Some lines do not belong to this project.');
            }
            if ($items->whereNotNull('procurement_lot_id')->isNotEmpty()) {
                throw new WorkflowViolation('Some lines are already in a lot.');
            }

            $lot = $project->lots()->create([
                'lot_no' => (int) $project->lots()->max('lot_no') + 1,
                'title' => $title,
                'approved_budget' => 0,
                'status' => 'open',
            ]);
            $project->items()->whereKey($projectItemIds)->update(['procurement_lot_id' => $lot->id]);
            CreateProcurementProject::recomputeBudgets($project);

            return $lot->fresh();
        });
    }
}
