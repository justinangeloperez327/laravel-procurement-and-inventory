<?php

namespace App\Policies;

use App\Domain\Identity\Authorizer;
use App\Domain\Planning\PlanningAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Market scopings and PPMPs: prepared by ppmp.create holders of the department, visible to the
 * department's planners and heads and to agency-wide planning readers. Never deleted.
 */
abstract class DepartmentPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && PlanningAccess::canSeeAny($user);
    }

    public function view(User $user, Model $plan): bool
    {
        return $user->is_active && PlanningAccess::canSeeDepartment($user, $plan->agency_id, $plan->department_id);
    }

    public function create(User $user): bool
    {
        return $user->is_active && app(Authorizer::class)->holdsAnywhere($user, 'ppmp.create');
    }

    public function update(User $user, Model $plan): bool
    {
        return $plan->isEditable() && $user->agency_id === $plan->agency_id && $user->hasPermission('ppmp.create', $plan->department);
    }

    public function delete(User $user, Model $plan): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
