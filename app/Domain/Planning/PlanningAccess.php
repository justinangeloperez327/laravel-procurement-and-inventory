<?php

namespace App\Domain\Planning;

use App\Domain\Identity\Authorizer;
use App\Models\User;

/** Who can see department plans (market scopings and PPMPs). */
final class PlanningAccess
{
    /** Agency-wide planning readers: APP consolidation and approval, budget certification, audit. */
    public const AGENCY_WIDE = ['app.consolidate', 'app.approve', 'budget.certify', 'audit.read'];

    /** Department-scoped planning permissions. */
    public const DEPARTMENT = ['ppmp.create', 'ppmp.approve'];

    /**
     * Departments whose plans the user may see: null means every department of their agency.
     *
     * @return list<int>|null
     */
    public static function departmentIds(User $user): ?array
    {
        if ($user->is_admin && $user->is_active) {
            return null;
        }
        $authorizer = app(Authorizer::class);
        foreach (self::AGENCY_WIDE as $permission) {
            if ($authorizer->departmentScope($user, $permission) === null) {
                return null;
            }
        }

        $ids = [];
        foreach (self::DEPARTMENT as $permission) {
            $scope = $authorizer->departmentScope($user, $permission);
            if ($scope === null) {
                return null;
            }
            $ids = [...$ids, ...$scope];
        }

        return array_values(array_unique($ids));
    }

    public static function canSeeAny(User $user): bool
    {
        return self::departmentIds($user) !== [];
    }

    public static function canSeeDepartment(User $user, int $agencyId, int $departmentId): bool
    {
        if (! ($user->is_admin && $user->is_active) && $user->agency_id !== $agencyId) {
            return false;
        }
        $ids = self::departmentIds($user);

        return $ids === null || in_array($departmentId, $ids, true);
    }
}
