<?php

namespace App\Policies;

use App\Domain\Planning\PlanningAccess;
use App\Models\AppVersion;
use App\Models\User;

/** The APP is visible to anyone in planning; versions are prepared by agency-wide app.consolidate holders. */
class AppVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && PlanningAccess::canSeeAny($user);
    }

    public function view(User $user, AppVersion $version): bool
    {
        return $this->viewAny($user) && (($user->is_admin && $user->is_active) || $user->agency_id === $version->app->agency_id);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasPermission('app.consolidate');
    }

    public function update(User $user, AppVersion $version): bool
    {
        return $version->isEditable() && $user->agency_id === $version->app->agency_id && $user->hasPermission('app.consolidate');
    }

    public function delete(User $user, AppVersion $version): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
