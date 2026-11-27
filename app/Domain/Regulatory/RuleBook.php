<?php

namespace App\Domain\Regulatory;

use App\Domain\Shared\WorkflowViolation;
use App\Enums\RuleStatus;
use App\Models\RegulatoryRuleVersion;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Looks up the approved regulatory rule version in effect on a date. Draft and retired versions are
 * never returned, so unreviewed interpretations cannot gate a transaction (docs/02, docs/08).
 *
 * Pass the transaction's governing date (for example the date procurement started), not today, so
 * transactions under a legacy regime keep their applicable rules (L-15).
 */
class RuleBook
{
    public function effective(string $ruleCode, CarbonInterface|string $on): ?RegulatoryRuleVersion
    {
        $date = Carbon::parse($on)->toDateString();

        return RegulatoryRuleVersion::query()
            ->where('rule_code', $ruleCode)
            ->where('status', RuleStatus::Approved)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>', $date))
            ->orderByDesc('version')
            ->first();
    }

    public function require(string $ruleCode, CarbonInterface|string $on): RegulatoryRuleVersion
    {
        return $this->effective($ruleCode, $on)
            ?? throw new WorkflowViolation("No approved rule {$ruleCode} is in effect on ".Carbon::parse($on)->toDateString().'.');
    }
}
