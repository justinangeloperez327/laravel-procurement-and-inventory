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

    /** Whether the user holds the permission in any department of their agency, directly or by delegation. */
    public function holdsAnywhere(User $user, string $permission, ?CarbonInterface $at = null): bool
    {
        return $this->departmentScope($user, $permission, $at) !== [];
    }

    /**
     * Where the user holds a permission: null means agency-wide, otherwise the list of department IDs
     * (empty when the permission is not held at all). Useful for scoping list queries.
     *
     * @return list<int>|null
     */
    public function departmentScope(User $user, string $permission, ?CarbonInterface $at = null): ?array
    {
        $at ??= now();
        if (! $user->exists || ! $user->is_active || $user->agency_id === null) {
            return [];
        }

        $direct = $user->roleAssignments()->active($at)->where('agency_id', $user->agency_id)
            ->whereHas('role.permissions', fn (Builder $q) => $q->where('code', $permission))
            ->pluck('department_id');

        $delegated = Delegation::query()->active($at)->with('delegator')
            ->where('delegate_id', $user->id)->where('agency_id', $user->agency_id)
            ->whereHas('permission', fn (Builder $q) => $q->where('code', $permission))
            ->get()
            ->filter(fn (Delegation $d) => $this->holdsDirectly($d->delegator, $permission, $d->agency_id,
                $d->department_id ? Department::find($d->department_id) : null, $at))
            ->pluck('department_id');

        $all = $direct->concat($delegated);
        if ($all->contains(null)) {
            return null;
        }

        return $all->unique()->map(fn ($id) => (int) $id)->values()->all();
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
        return $this->delegationUsed($user, $permission, $agencyId, $department, $at) !== null;
    }

    /**
     * The active delegation through which the user holds the permission, if any. Authority lapses when
     * the delegator loses it, and delegated authority cannot be re-delegated.
     */
    public function delegationUsed(User $user, string $permission, int $agencyId, ?Department $department, ?CarbonInterface $at = null): ?Delegation
    {
        $at ??= now();
        if (! $user->is_active) {
            return null;
        }

        return Delegation::query()
            ->active($at)
            ->with('delegator')
            ->where('delegate_id', $user->id)
            ->where('agency_id', $agencyId)
            ->where(fn (Builder $q) => $this->scope($q, $department))
            ->whereHas('permission', fn (Builder $q) => $q->where('code', $permission))
            ->orderBy('id')
            ->get()
            ->first(fn (Delegation $d) => $this->holdsDirectly($d->delegator, $permission, $agencyId, $department, $at));
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
