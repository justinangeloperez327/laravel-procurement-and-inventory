<?php

namespace App\Domain\Identity;

use App\Models\Delegation;
use App\Models\Department;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves scoped permission strings for a user.
 *
 * A permission is held when the user has an active role assignment granting it in the same agency
 * that is agency-wide or scoped to the target department, or an active delegation of it from a
 * user who still holds it directly. Technical administrator status never confers a permission.
 */
class Authorizer
{
    public function allows(User $user, string $permission, ?Department $department = null, ?CarbonInterface $at = null): bool
    {
        $at ??= now();

        if (! $user->exists || ! $user->is_active) {
            return false;
        }

        $agencyId = $department?->agency_id ?? $user->agency_id;
        if ($agencyId === null) {
            return false;
        }

        if ($this->holdsDirectly($user, $permission, $agencyId, $department, $at)) {
            return true;
        }

        return $this->holdsByDelegation($user, $permission, $agencyId, $department, $at);
    }

    public function holdsDirectly(User $user, string $permission, int $agencyId, ?Department $department, CarbonInterface $at): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->roleAssignments()
            ->active($at)
            ->where('agency_id', $agencyId)
            ->where(fn (Builder $q) => $this->scope($q, $department))
            ->whereHas('role.permissions', fn (Builder $q) => $q->where('code', $permission))
            ->exists();
    }

    private function holdsByDelegation(User $user, string $permission, int $agencyId, ?Department $department, CarbonInterface $at): bool
    {
        $delegations = Delegation::query()
            ->active($at)
            ->with('delegator')
            ->where('delegate_id', $user->id)
            ->where('agency_id', $agencyId)
            ->where(fn (Builder $q) => $this->scope($q, $department))
            ->whereHas('permission', fn (Builder $q) => $q->where('code', $permission))
            ->get();

        // Authority lapses when the delegator loses it; delegated authority cannot be re-delegated.
        return $delegations->contains(
            fn (Delegation $d) => $this->holdsDirectly($d->delegator, $permission, $agencyId, $department, $at)
        );
    }

    /** Agency-wide grants cover every department; department grants cover only that department. */
    private function scope(Builder $query, ?Department $department): void
    {
        $query->whereNull('department_id');
        if ($department !== null) {
            $query->orWhere('department_id', $department->id);
        }
    }
}
