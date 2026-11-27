<?php

namespace App\Policies;

use App\Models\Delegation;
use App\Models\User;

class DelegationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Delegation $delegation): bool
    {
        return ($user->is_admin && $user->is_active)
            || $user->hasPermission('audit.read')
            || in_array($user->id, [$delegation->delegator_id, $delegation->delegate_id], true);
    }

    /** Any active user holding a role may delegate authority they hold directly; the action validates it. */
    public function create(User $user): bool
    {
        return $user->is_active && $user->roleAssignments()->active()->exists();
    }

    public function update(User $user, Delegation $delegation): bool
    {
        return false;
    }

    public function revoke(User $user, Delegation $delegation): bool
    {
        return $delegation->revoked_at === null
            && $delegation->ends_at->isFuture()
            && ($user->id === $delegation->delegator_id || ($user->is_admin && $user->is_active));
    }

    public function delete(User $user, Delegation $delegation): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
