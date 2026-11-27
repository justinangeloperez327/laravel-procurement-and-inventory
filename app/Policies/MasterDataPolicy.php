<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Organization master data: maintained by system administrators, readable by auditors.
 * Records are never deleted; deactivate them instead so references and history survive.
 */
abstract class MasterDataPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->is_admin && $user->is_active) || $user->hasPermission('audit.read');
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_admin && $user->is_active;
    }

    public function update(User $user, Model $record): bool
    {
        return $user->is_admin && $user->is_active;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
