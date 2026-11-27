<?php

use App\Domain\Regulatory\ApproveRuleVersion;
use App\Domain\Regulatory\RetireRuleVersion;
use App\Domain\Regulatory\RuleBook;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\RuleStatus;
use App\Models\Agency;
use App\Models\RegulatoryRuleVersion;
use App\Models\User;

beforeEach(function () {
    seedRoles();
    $agency = Agency::factory()->create();
    $this->preparer = User::factory()->forAgency($agency)->create();
    $this->reviewer = User::factory()->forAgency($agency)->create();
    grant($this->reviewer, 'legal_reviewer');
    $this->draft = fn (array $attrs = []) => RegulatoryRuleVersion::factory()->create(array_merge([
        'rule_code' => 'SVP-CEILING', 'prepared_by' => $this->preparer->id, 'legal_reference' => 'IRR Sec. X',
        'approved_interpretation' => 'Interpretation text', 'test_reference' => 'tests/Feature/Example',
        'effective_from' => '2025-01-01', 'effective_to' => null,
    ], $attrs));
    $this->approve = app(ApproveRuleVersion::class);
    $this->book = app(RuleBook::class);
});

it('never returns draft rules', function () {
    ($this->draft)();

    expect($this->book->effective('SVP-CEILING', '2026-06-01'))->toBeNull()
        ->and(fn () => $this->book->require('SVP-CEILING', '2026-06-01'))->toThrow(WorkflowViolation::class);
});

it('applies the version in effect on the transaction date (legacy regime, L-15)', function () {
    $legacy = ($this->draft)(['version' => 1, 'effective_from' => '2016-01-01', 'effective_to' => '2025-01-01', 'legal_reference' => 'RA 9184 IRR']);
    $current = ($this->draft)(['version' => 2, 'effective_from' => '2025-01-01', 'legal_reference' => 'RA 12009 IRR']);
    ($this->approve)($this->reviewer, $legacy);
    ($this->approve)($this->reviewer, $current);

    expect($this->book->effective('SVP-CEILING', '2024-12-31')->is($legacy))->toBeTrue()
        ->and($this->book->effective('SVP-CEILING', '2025-01-01')->is($current))->toBeTrue()
        ->and($this->book->effective('SVP-CEILING', '2015-12-31'))->toBeNull();
});

it('requires a legal reviewer who is not the preparer', function () {
    expect(fn () => ($this->approve)($this->preparer, ($this->draft)()))->toThrow(WorkflowViolation::class, 'regulatory_rule.approve');

    grant($this->preparer, 'legal_reviewer');
    expect(fn () => ($this->approve)($this->preparer, ($this->draft)(['version' => 2])))->toThrow(WorkflowViolation::class, 'preparer');
});

it('requires interpretation, legal reference and test reference', function () {
    ($this->approve)($this->reviewer, ($this->draft)(['test_reference' => null]));
})->throws(WorkflowViolation::class, 'test_reference');

it('rejects overlapping approved versions', function () {
    ($this->approve)($this->reviewer, ($this->draft)(['version' => 1]));

    ($this->approve)($this->reviewer, ($this->draft)(['version' => 2, 'effective_from' => '2026-01-01']));
})->throws(WorkflowViolation::class, 'in effect during this period');

it('freezes approved versions and allows retirement with a reason', function () {
    $version = ($this->approve)($this->reviewer, ($this->draft)());

    expect(fn () => $version->update(['approved_interpretation' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $version->delete())->toThrow(LogicException::class);

    app(RetireRuleVersion::class)($this->reviewer, $version, 'Superseded by GPPB resolution');
    expect($version->fresh()->status)->toBe(RuleStatus::Retired)
        ->and($this->book->effective('SVP-CEILING', '2026-06-01'))->toBeNull();
});
