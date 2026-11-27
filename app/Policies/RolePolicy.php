<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** The role catalog is versioned in code (RoleCatalog) and synced; it is read-only in the UI. */
class RolePolicy extends MasterDataPolicy
{
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $record): bool
    {
        return false;
    }
}
