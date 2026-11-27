<?php

namespace App\Domain\Planning;

use App\Domain\Shared\WorkflowViolation;
use App\Enums\PlanStatus;
use App\Models\AuditLog;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Starts a new draft version of an approved PPMP, copying its items. The approved version stays in
 * force until the revision is approved, which then supersedes it.
 */
class RevisePpmp
{
    public function __invoke(User $actor, Ppmp $approved, string $reason): Ppmp
    {
        PlanGuards::requirePermission($actor, 'ppmp.create', $approved->department);
        if ($approved->status !== PlanStatus::Approved) {
            throw new WorkflowViolation('Only the approved version of a PPMP can be revised.');
        }
        if (blank($reason)) {
            throw new WorkflowViolation('A reason for the revision is required.');
        }

        return DB::transaction(function () use ($actor, $approved, $reason) {
            // Serialize revisions of the same PPMP.
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["ppmp:{$approved->agency_id}:{$approved->reference_no}"]);

            $open = $approved->versions()->whereIn('status', [PlanStatus::Draft, PlanStatus::Submitted, PlanStatus::Returned])->exists();
            if ($open) {
                throw new WorkflowViolation('A revision of this PPMP is already in progress.');
            }

            $revision = Ppmp::create([
                'agency_id' => $approved->agency_id,
                'department_id' => $approved->department_id,
                'fiscal_year_id' => $approved->fiscal_year_id,
                'reference_no' => $approved->reference_no,
                'version' => $approved->versions()->max('version') + 1,
                'parent_ppmp_id' => $approved->id,
                'status' => PlanStatus::Draft,
                'total_amount' => $approved->total_amount,
                'prepared_by' => $actor->id,
                'remarks' => $reason,
            ]);

            $approved->items()->get()->each(fn (PpmpItem $item) => $revision->items()->create(
                $item->only(['item_id', 'market_scoping_item_id', 'description', 'unit_of_measure_id', 'quantity',
                    'estimated_unit_cost', 'proposed_mode', 'schedule_start', 'schedule_end', 'account_code_id', 'fund_source_id', 'remarks'])
            ));

            AuditLog::record('ppmp.revision_started', $revision, null, ['from_version' => $approved->version], ['reason' => $reason]);

            return $revision;
        });
    }
}
