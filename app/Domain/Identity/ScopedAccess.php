<?php

namespace App\Domain\Identity;

use App\Models\User;

/**
 * Department-scoped visibility for department records (plans, purchase requests): agency-wide
 * permissions see every department; department permissions see only their departments.
 */
final class ScopedAccess
{
    /**
     * @param  list<string>  $agencyWide
     * @param  list<string>  $department
     * @return list<int>|null null means every department of the user's agency
     */
    public static function departmentIds(User $user, array $agencyWide, array $department): ?array
    {
        if ($user->is_admin && $user->is_active) {
            return null;
        }
        $authorizer = app(Authorizer::class);
        foreach ($agencyWide as $permission) {
            if ($authorizer->departmentScope($user, $permission) === null) {
                return null;
            }
        }

        $ids = [];
        foreach ($department as $permission) {
            $scope = $authorizer->departmentScope($user, $permission);
            if ($scope === null) {
                return null;
            }
            $ids = [...$ids, ...$scope];
        }

        return array_values(array_unique($ids));
    }

    /** @param  list<string>  $agencyWide  @param  list<string>  $department */
    public static function canSeeDepartment(User $user, int $agencyId, int $departmentId, array $agencyWide, array $department): bool
    {
        if (! ($user->is_admin && $user->is_active) && $user->agency_id !== $agencyId) {
            return false;
        }
        $ids = self::departmentIds($user, $agencyWide, $department);

        return $ids === null || in_array($departmentId, $ids, true);
    }
}
