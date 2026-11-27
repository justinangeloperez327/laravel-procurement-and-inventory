<?php

namespace App\Domain\Approvals;

use App\Models\ApprovalAction;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Approval history relation for Approvable models. */
trait HasApprovals
{
    public function approvalActions(): MorphMany
    {
        return $this->morphMany(ApprovalAction::class, 'approvable')->orderBy('id');
    }
}
