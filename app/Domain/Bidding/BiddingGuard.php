<?php

namespace App\Domain\Bidding;

use App\Domain\Compliance\ConflictDeclarations;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\ProcurementProjectStatus;
use App\Models\ProcurementProject;
use App\Models\Supplier;
use App\Models\User;

final class BiddingGuard
{
    public static function require(User $actor, string $permission, ProcurementProject $project): void
    {
        if (! $actor->hasPermission($permission)) {
            throw new WorkflowViolation("You do not hold {$permission}.");
        }
        if ($project->agency_id !== $actor->agency_id) {
            throw new WorkflowViolation('The procurement project belongs to another agency.');
        }
    }

    /** @param  list<ProcurementProjectStatus>  $statuses */
    public static function requireStatus(ProcurementProject $project, array $statuses): void
    {
        if (! in_array($project->status, $statuses, true)) {
            throw new WorkflowViolation("Not allowed while the project is {$project->status->label()}.");
        }
    }

    /** Evaluators and post-qualifiers must have declared and have no conflict with the project or supplier. */
    public static function requireIndependent(User $actor, ProcurementProject $project, ?Supplier $supplier = null): void
    {
        if (! ConflictDeclarations::hasDeclared($actor, $project)) {
            throw new WorkflowViolation("Declare whether you have a conflict of interest in {$project->reference_no} first.");
        }
        if (ConflictDeclarations::mustRecuse($actor, $project) || ($supplier && ConflictDeclarations::mustRecuse($actor, $supplier))) {
            throw new WorkflowViolation('You declared a conflict of interest and must recuse from this evaluation.');
        }
    }
}
