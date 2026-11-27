<?php

namespace App\Domain\Regulatory;

use App\Domain\Shared\WorkflowViolation;
use App\Enums\RuleStatus;
use App\Models\AuditLog;
use App\Models\RegulatoryRuleVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Legal review sign-off that makes a rule version enforceable. Requires the evidence listed in the
 * docs/02 regulatory source policy, a reviewer other than the preparer, and no overlap with another
 * approved version of the same rule.
 */
class ApproveRuleVersion
{
    public function __invoke(User $reviewer, RegulatoryRuleVersion $version): RegulatoryRuleVersion
    {
        if (! $reviewer->hasPermission('regulatory_rule.approve')) {
            throw new WorkflowViolation('You do not hold regulatory_rule.approve.');
        }
        if ($version->prepared_by === $reviewer->id) {
            throw new WorkflowViolation('The preparer of a rule version may not approve it.');
        }

        $missing = collect(['legal_reference', 'approved_interpretation', 'test_reference'])
            ->filter(fn (string $field) => blank($version->{$field}))
            ->all();
        if ($missing !== []) {
            throw new WorkflowViolation('Complete these fields before approval: '.implode(', ', $missing).'.');
        }

        return DB::transaction(function () use ($reviewer, $version) {
            // Serialize approvals of the same rule code so overlap checks cannot race.
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$version->rule_code]);

            $locked = RegulatoryRuleVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== RuleStatus::Draft) {
                throw new WorkflowViolation("Only draft versions can be approved; this one is {$locked->status->value}.");
            }

            $overlap = RegulatoryRuleVersion::query()
                ->where('rule_code', $locked->rule_code)
                ->where('status', RuleStatus::Approved)
                ->whereKeyNot($locked->id)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>', $locked->effective_from))
                ->when($locked->effective_to, fn ($q) => $q->whereDate('effective_from', '<', $locked->effective_to))
                ->exists();
            if ($overlap) {
                throw new WorkflowViolation('Another approved version of this rule is in effect during this period. End-date or retire it first.');
            }

            $locked->forceFill([
                'status' => RuleStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();

            AuditLog::record('regulatory_rule.approved', $locked, null, ['rule_code' => $locked->rule_code, 'version' => $locked->version]);

            return $locked;
        });
    }
}
