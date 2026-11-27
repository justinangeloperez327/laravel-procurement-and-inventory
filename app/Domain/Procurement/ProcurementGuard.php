<?php

namespace App\Domain\Procurement;

use App\Domain\Shared\WorkflowViolation;
use App\Models\ProcurementProject;
use App\Models\User;

final class ProcurementGuard
{
    public static function require(User $actor, string $permission, ?ProcurementProject $project = null): void
    {
        if (! $actor->hasPermission($permission)) {
            throw new WorkflowViolation("You do not hold {$permission}.");
        }
        if ($project !== null && $project->agency_id !== $actor->agency_id) {
            throw new WorkflowViolation('The procurement project belongs to another agency.');
        }
    }
}
