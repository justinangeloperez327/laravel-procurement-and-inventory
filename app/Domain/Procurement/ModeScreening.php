<?php

namespace App\Domain\Procurement;

use App\Domain\Regulatory\RuleBook;
use App\Domain\Shared\Money;
use App\Enums\ProcurementMode;
use App\Models\ProcurementModeDefinition;
use App\Models\ProcurementModeRule;
use App\Models\ProcurementProject;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Screens a candidate procurement mode for a project against the approved, effective rule versions
 * linked to that mode (docs/08; L-04).
 *
 * Rules are data, prepared by procurement staff and approved by a legal reviewer (phase 2). No peso
 * thresholds are hardcoded. Supported condition keys in a rule version's `conditions`:
 *   abc_max            project ABC must not exceed this amount (e.g. "1000000.00")
 *   abc_min            project ABC must be at least this amount
 *   categories         comma-separated procurement categories allowed (goods, infrastructure, consulting_services)
 *   requires_evidence  comma-separated evidence keys the justification must supply (e.g. "original_contract,price_comparison")
 * Unknown keys cannot be evaluated automatically and are reported for the reviewer to confirm.
 *
 * Competitive bidding is the default method and needs no screening rule. Every alternative mode needs
 * at least one approved rule in effect, otherwise it cannot be used.
 */
class ModeScreening
{
    public const CONDITION_KEYS = ['abc_max', 'abc_min', 'categories', 'requires_evidence'];

    public function __construct(private RuleBook $rules) {}

    /** @param  array<string, string|null>  $evidence */
    public function screen(ProcurementProject $project, ProcurementMode $mode, array $evidence = [], ?CarbonInterface $on = null): ScreeningResult
    {
        // The governing date is when procurement started, so later rule changes do not move the goalposts (L-15).
        $date = Carbon::parse($on ?? $project->created_at ?? now())->startOfDay();
        $definition = ProcurementModeDefinition::where('code', $mode)->first();
        $links = $definition
            ? ProcurementModeRule::with('regulatoryRuleVersion')->where('procurement_mode_id', $definition->id)->get()
            : collect();

        $checks = [];
        foreach ($links as $link) {
            $code = $link->regulatoryRuleVersion->rule_code;
            $version = $this->rules->effective($code, $date);
            if ($version === null) {
                $checks[] = $this->check($code, null, $link->rule_type, $link->is_blocking, false, "No approved version of {$code} is in effect on {$date->toDateString()}.");

                continue;
            }
            foreach ($version->conditions ?? [] as $key => $value) {
                $checks[] = $this->evaluate($project, $evidence, $code, $version->version, $link->rule_type, $link->is_blocking, (string) $key, (string) $value);
            }
            if (($version->conditions ?? []) === []) {
                $checks[] = $this->check($code, $version->version, $link->rule_type, false, false,
                    "{$code} v{$version->version} has no machine-checkable conditions; the reviewer must confirm it is met.");
            }
        }

        if ($mode !== ProcurementMode::CompetitiveBidding && $links->isEmpty()) {
            $checks[] = $this->check('mode-rules', null, 'eligibility', true, false,
                "{$mode->label()} has no approved screening rules. Record and approve the applicable rules before using this mode.");
        }

        return new ScreeningResult($mode->value, $date->toDateString(), $checks);
    }

    private function evaluate(ProcurementProject $project, array $evidence, string $code, int $version, string $type, bool $blocking, string $key, string $value): array
    {
        $abc = Money::toCents((string) $project->approved_budget);

        return match ($key) {
            'abc_max' => $this->check($code, $version, $type, $blocking, $abc <= Money::toCents($value),
                'ABC '.Money::format((string) $project->approved_budget).' must not exceed '.Money::format($value).'.', ['abc_max_cents' => Money::toCents($value)]),
            'abc_min' => $this->check($code, $version, $type, $blocking, $abc >= Money::toCents($value),
                'ABC '.Money::format((string) $project->approved_budget).' must be at least '.Money::format($value).'.'),
            'categories' => $this->check($code, $version, $type, $blocking,
                in_array($project->category->value, array_map('trim', explode(',', $value)), true),
                "Category {$project->category->label()} must be one of: {$value}."),
            'requires_evidence' => (function () use ($code, $version, $type, $blocking, $evidence, $value) {
                $missing = array_values(array_filter(array_map('trim', explode(',', $value)), fn ($k) => $k !== '' && blank($evidence[$k] ?? null)));

                return $this->check($code, $version, $type, $blocking, $missing === [],
                    $missing === [] ? 'Required evidence supplied.' : 'Missing evidence: '.implode(', ', $missing).'.');
            })(),
            default => $this->check($code, $version, $type, false, false, "Condition {$key} = {$value} must be confirmed by the reviewer."),
        };
    }

    private function check(string $code, ?int $version, string $type, bool $blocking, bool $passed, string $message, array $extra = []): array
    {
        return ['rule_code' => $code, 'version' => $version, 'rule_type' => $type, 'blocking' => $blocking, 'passed' => $passed, 'message' => $message, ...$extra];
    }
}
