<?php

namespace App\Domain\Approvals;

use App\Models\Department;

/**
 * A record that is routed through an approval workflow.
 */
interface Approvable
{
    /** Key matching approval_workflows.subject_type, e.g. "purchase_request". */
    public function approvalSubjectType(): string;

    public function approvalAgencyId(): int;

    /** Department used for department-scoped approval steps. */
    public function approvalDepartment(): ?Department;

    /** The user who initiated the record; they may never approve it. */
    public function approvalInitiatorId(): ?int;
}
