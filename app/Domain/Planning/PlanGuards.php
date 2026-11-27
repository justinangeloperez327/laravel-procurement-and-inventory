<?php

namespace App\Domain\Planning;

use App\Domain\Identity\Authorizer;
use App\Domain\Shared\WorkflowViolation;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\User;

/** Common preconditions for creating planning records. */
final class PlanGuards
{
    public static function requirePermission(User $actor, string $permission, ?Department $department = null): void
    {
        if (! app(Authorizer::class)->allows($actor, $permission, $department)) {
            throw new WorkflowViolation("You do not hold {$permission}".($department ? " for {$department->name}" : '').'.');
        }
    }

    public static function requireOpenFiscalYear(FiscalYear $fiscalYear, int $agencyId): void
    {
        if ($fiscalYear->agency_id !== $agencyId) {
            throw new WorkflowViolation('The fiscal year belongs to another agency.');
        }
        if ($fiscalYear->isClosed()) {
            throw new WorkflowViolation("Fiscal year {$fiscalYear->year} is closed.");
        }
    }
}
