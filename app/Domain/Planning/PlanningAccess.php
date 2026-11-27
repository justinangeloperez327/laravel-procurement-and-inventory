<?php

namespace App\Domain\Planning;

use App\Domain\Identity\ScopedAccess;
use App\Models\User;

/** Who can see department plans (market scopings and PPMPs). */
final class PlanningAccess
{
    /** Agency-wide planning readers: APP consolidation and approval, budget certification, audit. */
    public const AGENCY_WIDE = ['app.consolidate', 'app.approve', 'budget.certify', 'audit.read'];

    /** Department-scoped planning permissions. */
    public const DEPARTMENT = ['ppmp.create', 'ppmp.approve'];

    /** @return list<int>|null null means every department of the user's agency */
    public static function departmentIds(User $user): ?array
    {
        return ScopedAccess::departmentIds($user, self::AGENCY_WIDE, self::DEPARTMENT);
    }

    public static function canSeeAny(User $user): bool
    {
        return self::departmentIds($user) !== [];
    }

    public static function canSeeDepartment(User $user, int $agencyId, int $departmentId): bool
    {
        return ScopedAccess::canSeeDepartment($user, $agencyId, $departmentId, self::AGENCY_WIDE, self::DEPARTMENT);
    }
}
