<?php

namespace App\Domain\Requests;

use App\Domain\Identity\ScopedAccess;
use App\Models\User;

/** Who can see purchase requests. */
final class PurchaseRequestAccess
{
    /** Agency-wide: budget, procurement, BAC, HoPE and audit. */
    public const AGENCY_WIDE = ['budget.certify', 'budget.reserve', 'rfq.manage', 'procurement_mode.review', 'app.approve', 'audit.read'];

    /** Department-scoped: requesters and approving heads. */
    public const DEPARTMENT = ['purchase_request.create', 'purchase_request.submit', 'purchase_request.approve'];

    /** @return list<int>|null */
    public static function departmentIds(User $user): ?array
    {
        return ScopedAccess::departmentIds($user, self::AGENCY_WIDE, self::DEPARTMENT);
    }

    public static function canSeeDepartment(User $user, int $agencyId, int $departmentId): bool
    {
        return ScopedAccess::canSeeDepartment($user, $agencyId, $departmentId, self::AGENCY_WIDE, self::DEPARTMENT);
    }
}
