<?php

namespace App\Domain\Budget;

use App\Domain\Planning\PlanGuards;
use App\Domain\Shared\WorkflowViolation;
use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Verification by a second budget officer that the allotment matches its authorization. */
class ApproveBudgetAllocation
{
    public function __invoke(User $actor, BudgetAllocation $allocation): BudgetAllocation
    {
        PlanGuards::requirePermission($actor, 'budget.allocate');
        if ($allocation->recorded_by === $actor->id) {
            throw new WorkflowViolation('The officer who recorded an allotment may not approve it.');
        }

        return DB::transaction(function () use ($actor, $allocation) {
            $locked = BudgetAllocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            if ($locked->agency_id !== $actor->agency_id) {
                throw new WorkflowViolation('The allotment belongs to another agency.');
            }
            if ($locked->status !== BudgetAllocation::STATUS_DRAFT) {
                throw new WorkflowViolation('Only draft allotments can be approved.');
            }

            $locked->forceFill(['status' => BudgetAllocation::STATUS_APPROVED, 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            AuditLog::record('budget_allocation.approved', $locked, null, ['amount' => $locked->amount]);

            return $locked;
        });
    }
}
