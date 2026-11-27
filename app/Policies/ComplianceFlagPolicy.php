<?php

namespace App\Policies;

use App\Models\ComplianceFlag;
use App\Models\User;

/** Flags are raised by the system or domain actions and resolved only through the review action. */
class ComplianceFlagPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->is_admin && $user->is_active) || $user->hasPermission('compliance.review') || $user->hasPermission('audit.read');
    }

    public function view(User $user, ComplianceFlag $flag): bool
    {
        return $this->viewAny($user) && (($user->is_admin && $user->is_active) || $user->agency_id === $flag->agency_id);
    }

    public function review(User $user, ComplianceFlag $flag): bool
    {
        return in_array($flag->status, ['open', 'escalated'], true) && $flag->raised_by !== $user->id
            && $user->agency_id === $flag->agency_id && $user->hasPermission('compliance.review');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ComplianceFlag $flag): bool
    {
        return false;
    }

    public function delete(User $user, ComplianceFlag $flag): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
