<?php

namespace App\Domain\Compliance;

use App\Domain\Shared\WorkflowViolation;
use App\Models\AuditLog;
use App\Models\ConflictDeclaration;
use App\Models\ProcurementProject;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Conflict-of-interest declarations (L-11). Anyone taking part in a procurement declares whether they
 * have a conflict with the project or a supplier. A declared conflict raises a compliance flag, and
 * later phases must keep conflicted users out of evaluation and award (mustRecuse()).
 */
class ConflictDeclarations
{
    public const ACTIONS = [
        'recused' => 'Recused from the procurement',
        'disclosed_only' => 'Disclosed; continuing with approval',
        'divested' => 'Interest divested',
    ];

    public function __construct(private ComplianceFlags $flags) {}

    public function declare(
        User $actor,
        ?ProcurementProject $project,
        ?Supplier $supplier,
        bool $hasConflict,
        ?string $nature = null,
        ?string $actionTaken = null,
    ): ConflictDeclaration {
        if (! $actor->is_active || $actor->agency_id === null) {
            throw new WorkflowViolation('Only active agency users can make declarations.');
        }
        if ($project === null && $supplier === null) {
            throw new WorkflowViolation('Declare against a procurement project, a supplier, or both.');
        }
        foreach ([$project, $supplier] as $subject) {
            if ($subject !== null && $subject->agency_id !== $actor->agency_id) {
                throw new WorkflowViolation('You can only declare for records of your agency.');
            }
        }
        if ($hasConflict && (blank($nature) || ! array_key_exists((string) $actionTaken, self::ACTIONS))) {
            throw new WorkflowViolation('Describe the conflict and the action taken.');
        }

        return DB::transaction(function () use ($actor, $project, $supplier, $hasConflict, $nature, $actionTaken) {
            $declaration = ConflictDeclaration::create([
                'user_id' => $actor->id,
                'procurement_project_id' => $project?->id,
                'supplier_id' => $supplier?->id,
                'declared_at' => now(),
                'has_conflict' => $hasConflict,
                'nature' => $hasConflict ? $nature : null,
                'action_taken' => $hasConflict ? $actionTaken : null,
            ]);
            AuditLog::record('conflict.declared', $declaration, null, ['has_conflict' => $hasConflict]);

            if ($hasConflict) {
                $severity = $actionTaken === 'recused' ? 'warning' : 'blocking';
                $this->flags->raise($actor->agency_id, $declaration, 'conflict_of_interest', $severity,
                    "{$actor->name} declared a conflict: {$nature} (".self::ACTIONS[$actionTaken].').');
            }

            return $declaration;
        });
    }

    public function review(User $reviewer, ConflictDeclaration $declaration, string $notes): ConflictDeclaration
    {
        if (! $reviewer->hasPermission('compliance.review')) {
            throw new WorkflowViolation('You do not hold compliance.review.');
        }
        if ($declaration->user_id === $reviewer->id) {
            throw new WorkflowViolation('You cannot review your own declaration.');
        }
        if ($declaration->user->agency_id !== $reviewer->agency_id) {
            throw new WorkflowViolation('The declaration belongs to another agency.');
        }
        if ($declaration->reviewed_at !== null) {
            throw new WorkflowViolation('The declaration is already reviewed.');
        }
        if (blank($notes)) {
            throw new WorkflowViolation('Review notes are required.');
        }

        $declaration->forceFill(['reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_notes' => $notes])->save();
        AuditLog::record('conflict.reviewed', $declaration, null, null, ['notes' => $notes]);

        return $declaration;
    }

    /** Whether the user's latest declaration for the project or supplier reports a conflict. */
    public static function mustRecuse(User $user, ProcurementProject|Supplier $subject): bool
    {
        $column = $subject instanceof ProcurementProject ? 'procurement_project_id' : 'supplier_id';

        return (bool) ConflictDeclaration::query()
            ->where('user_id', $user->id)->where($column, $subject->id)
            ->latest('declared_at')->latest('id')
            ->value('has_conflict');
    }

    /** Whether the user has made any declaration for the project (required before evaluating it). */
    public static function hasDeclared(User $user, ProcurementProject $project): bool
    {
        return ConflictDeclaration::where('user_id', $user->id)->where('procurement_project_id', $project->id)->exists();
    }
}
