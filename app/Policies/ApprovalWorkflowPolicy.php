<?php

namespace App\Policies;

use App\Models\ApprovalAction;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Approval routes are configured by system administrators from approved office orders. A workflow
 * that has already been used is frozen so in-flight and past approvals keep their meaning; publish
 * a new workflow with a later effective date instead.
 */
class ApprovalWorkflowPolicy extends MasterDataPolicy
{
    public function update(User $user, Model $record): bool
    {
        return $record instanceof ApprovalWorkflow && ! self::inUse($record) && parent::update($user, $record);
    }

    public static function inUse(ApprovalWorkflow $workflow): bool
    {
        return ApprovalAction::whereIn('approval_step_id', $workflow->steps()->select('id'))->exists();
    }
}
