<?php

namespace App\Policies;

use App\Models\User;

/** Assignments change only through GrantRole/RevokeRole; generic CRUD is denied. */
class RoleAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->is_admin && $user->is_active) || $user->hasPermission('audit.read');
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user): bool
    {
        return false;
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
