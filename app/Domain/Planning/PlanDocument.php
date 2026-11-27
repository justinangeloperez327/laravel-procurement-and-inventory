<?php

namespace App\Domain\Planning;

use App\Domain\Approvals\Approvable;
use App\Domain\Shared\WorkflowViolation;
use App\Models\User;

/**
 * Market scopings, PPMPs and APP versions: drafted, submitted, approved through the approval engine,
 * and frozen once submitted. Revisions are new versions, never edits.
 */
interface PlanDocument extends Approvable
{
    /** Permission needed (in approvalDepartment(), or agency-wide when null) to prepare and submit. */
    public function submissionPermission(): string;

    /** @throws WorkflowViolation when the plan is incomplete. */
    public function assertReadyForSubmission(): void;

    /** Recomputes total_amount from the lines in the database (exact decimal arithmetic). */
    public function recalculateTotal(): void;

    /** Side effects of final approval, e.g. superseding the previous version. */
    public function afterApproved(User $approver): void;
}
