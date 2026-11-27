<?php

namespace App\Policies;

use App\Models\BacCommittee;
use App\Models\User;

class BacCommitteePolicy
{
    public function viewAny(User $user): bool
    {
        return ProcurementProjectPolicy::canRead($user) || $user->hasPermission('bac.constitute');
    }

    public function view(User $user, BacCommittee $committee): bool
    {
        return $this->viewAny($user) && (($user->is_admin && $user->is_active) || $user->agency_id === $committee->agency_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('bac.constitute');
    }

    public function update(User $user, BacCommittee $committee): bool
    {
        return $user->agency_id === $committee->agency_id && $user->hasPermission('bac.constitute');
    }

    public function delete(User $user, BacCommittee $committee): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
