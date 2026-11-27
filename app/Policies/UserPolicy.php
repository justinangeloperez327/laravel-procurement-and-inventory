<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends MasterDataPolicy
{
    /** Users are deactivated, never deleted, so their audit history stays attributable. */
    public function update(User $user, Model $record): bool
    {
        return parent::update($user, $record);
    }
}
