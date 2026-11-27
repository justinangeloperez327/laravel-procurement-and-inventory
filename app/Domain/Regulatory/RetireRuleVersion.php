<?php

namespace App\Domain\Regulatory;

use App\Domain\Shared\WorkflowViolation;
use App\Enums\RuleStatus;
use App\Models\AuditLog;
use App\Models\RegulatoryRuleVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Withdraws an approved rule version from enforcement. The record and its history are kept. */
class RetireRuleVersion
{
    public function __invoke(User $reviewer, RegulatoryRuleVersion $version, string $reason): RegulatoryRuleVersion
    {
        if (! $reviewer->hasPermission('regulatory_rule.approve')) {
            throw new WorkflowViolation('You do not hold regulatory_rule.approve.');
        }
        if (blank($reason)) {
            throw new WorkflowViolation('A reason is required to retire a rule version.');
        }

        return DB::transaction(function () use ($version, $reason) {
            $locked = RegulatoryRuleVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== RuleStatus::Approved) {
                throw new WorkflowViolation('Only approved versions can be retired.');
            }

            $locked->forceFill(['status' => RuleStatus::Retired])->save();
            AuditLog::record('regulatory_rule.retired', $locked, null, null, ['reason' => $reason]);

            return $locked;
        });
    }
}
