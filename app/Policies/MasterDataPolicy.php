<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Master data: maintained by system administrators or holders of $managePermission, readable by
 * auditors. Non-administrators can only touch records of their own agency. Records are never
 * deleted; deactivate them instead so references and history survive.
 */
abstract class MasterDataPolicy
{
    /** Permission (agency-wide) that allows maintaining this kind of record, besides is_admin. */
    protected ?string $managePermission = null;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $user->hasPermission('audit.read') || $this->canManage($user);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user) && $this->sameAgency($user, $record);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $this->canManage($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->create($user) && $this->sameAgency($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    protected function isAdmin(User $user): bool
    {
        return $user->is_admin && $user->is_active;
    }

    protected function canManage(User $user): bool
    {
        return $this->managePermission !== null && $user->hasPermission($this->managePermission);
    }

    protected function sameAgency(User $user, Model $record): bool
    {
        if ($this->isAdmin($user) || ! array_key_exists('agency_id', $record->getAttributes())) {
            return true;
        }

        return $record->getAttribute('agency_id') === $user->agency_id;
    }
}
