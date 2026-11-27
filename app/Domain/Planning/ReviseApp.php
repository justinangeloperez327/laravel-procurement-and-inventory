<?php

namespace App\Domain\Planning;

use App\Domain\Shared\WorkflowViolation;
use App\Enums\PlanStatus;
use App\Models\AnnualProcurementPlan;
use App\Models\AppItem;
use App\Models\AppVersion;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Starts a new draft APP version from the approved one, copying items and their PPMP lineage. The
 * approved version stays in force until the revision is approved.
 */
class ReviseApp
{
    public function __invoke(User $actor, AnnualProcurementPlan $app, string $reason): AppVersion
    {
        PlanGuards::requirePermission($actor, 'app.consolidate');
        if (blank($reason)) {
            throw new WorkflowViolation('A reason for the revision is required.');
        }

        return DB::transaction(function () use ($actor, $app, $reason) {
            AnnualProcurementPlan::whereKey($app->id)->lockForUpdate()->first();

            if ($app->versions()->whereIn('status', [PlanStatus::Draft, PlanStatus::Submitted, PlanStatus::Returned])->exists()) {
                throw new WorkflowViolation('A version of this APP is already being prepared.');
            }
            $current = $app->versions()->where('status', PlanStatus::Approved)->first()
                ?? throw new WorkflowViolation('Only an approved APP can be revised.');

            $revision = $app->versions()->create([
                'version' => $app->versions()->max('version') + 1,
                'status' => PlanStatus::Draft,
                'is_indicative' => $current->is_indicative,
                'total_amount' => $current->total_amount,
                'prepared_by' => $actor->id,
                'revision_reason' => $reason,
                'supersedes_id' => $current->id,
            ]);

            $current->items()->with('sources')->get()->each(function (AppItem $item) use ($revision) {
                $copy = $revision->items()->create($item->only([
                    'code', 'description', 'item_category_id', 'procurement_mode', 'estimated_budget', 'fund_source_id',
                    'advertisement_on', 'opening_on', 'award_on', 'contract_signing_on', 'remarks',
                ]));
                foreach ($item->sources as $source) {
                    $copy->sources()->create($source->only(['ppmp_item_id', 'quantity', 'amount']));
                }
            });

            AuditLog::record('app.revision_started', $revision, null, ['from_version' => $current->version], ['reason' => $reason]);

            return $revision;
        });
    }
}
