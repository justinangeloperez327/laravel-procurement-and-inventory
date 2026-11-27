<?php

namespace App\Policies;

use App\Domain\Identity\Authorizer;
use App\Models\ProcurementProject;
use App\Models\User;

class ProcurementProjectPolicy
{
    public const READERS = ['procurement.manage', 'procurement_mode.review', 'procurement_mode.approve', 'bac.recommend', 'bac.meeting',
        'rfq.manage', 'bid.evaluate', 'budget.certify', 'compliance.review', 'audit.read'];

    public static function canRead(User $user): bool
    {
        if ($user->is_admin && $user->is_active) {
            return true;
        }
        $authorizer = app(Authorizer::class);

        return collect(self::READERS)->contains(fn (string $p) => $authorizer->holdsAnywhere($user, $p));
    }

    public function viewAny(User $user): bool
    {
        return self::canRead($user);
    }

    public function view(User $user, ProcurementProject $project): bool
    {
        return self::canRead($user) && (($user->is_admin && $user->is_active) || $user->agency_id === $project->agency_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('procurement.manage');
    }

    public function update(User $user, ProcurementProject $project): bool
    {
        return $project->isEditable() && $user->agency_id === $project->agency_id && $user->hasPermission('procurement.manage');
    }

    public function delete(User $user, ProcurementProject $project): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
