<?php

use App\Domain\Budget\AdjustBudget;
use App\Domain\Budget\ApproveBudgetAllocation;
use App\Domain\Budget\BudgetLedger;
use App\Domain\Budget\CreateBudgetAllocation;
use App\Domain\Shared\Money;
use App\Domain\Shared\WorkflowViolation;
use App\Models\AccountCode;
use App\Models\BudgetAllocation;
use App\Models\BudgetDisbursement;
use App\Models\BudgetObligation;
use App\Models\BudgetReservation;
use App\Models\FundSource;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->c = planningContext();
    $this->fund = FundSource::factory()->create(['agency_id' => $this->c->agency->id]);
    $this->create = fn (string $amount = '1000000.00') => app(CreateBudgetAllocation::class)(
        $this->c->budget, $this->c->fy, $this->fund, AccountCode::factory()->create(), $amount, 'SARO-2027-001', $this->c->dept,
    );
    $this->approved = fn (string $amount = '1000000.00') => app(ApproveBudgetAllocation::class)($this->c->budget2, ($this->create)($amount));
    $this->adjust = app(AdjustBudget::class);
    $this->ledger = app(BudgetLedger::class);
});

it('records a numbered draft allotment and requires a second officer to approve it', function () {
    $allocation = ($this->create)();

    expect($allocation->reference_no)->toBe('ALLOT-2027-0001')
        ->and(fn () => app(ApproveBudgetAllocation::class)($this->c->budget, $allocation))->toThrow(WorkflowViolation::class, 'recorded');

    $approved = app(ApproveBudgetAllocation::class)($this->c->budget2, $allocation);
    expect($approved->status)->toBe('approved')->and($approved->approved_by)->toBe($this->c->budget2->id);
});

it('requires budget.allocate', function () {
    app(CreateBudgetAllocation::class)($this->c->head, $this->c->fy, $this->fund, AccountCode::factory()->create(), '100', 'SARO');
})->throws(WorkflowViolation::class, 'budget.allocate');

it('freezes approved allotments; amounts change only through adjustments', function () {
    $allocation = ($this->approved)();

    $allocation->update(['amount' => '2000000.00']);
})->throws(LogicException::class);

it('reconciles augmentations, reductions and realignments', function () {
    $a = ($this->approved)('1000000.00');
    $b = ($this->approved)('500000.00');

    $this->adjust->augment($this->c->budget, $a, '250000.50', 'SARO-2027-002', 'Supplemental', 'k1');
    $this->adjust->reduce($this->c->budget, $a, '100000.25', 'DBM-letter', 'Savings', 'k2');
    $this->adjust->realign($this->c->budget, $a, $b, '50000.00', 'HoPE-approval-12', 'Shift to ICT', 'k3');

    $ba = $this->ledger->balance($a);
    $bb = $this->ledger->balance($b);
    expect(Money::fromCents($ba->adjusted()))->toBe('1100000.25')
        ->and(Money::fromCents($ba->available()))->toBe('1100000.25')
        ->and(Money::fromCents($bb->adjusted()))->toBe('550000.00')
        ->and(DB::table('budget_adjustments')->where('idempotency_key', 'k3:in')->value('related_adjustment_id'))
        ->toBe(DB::table('budget_adjustments')->where('idempotency_key', 'k3:out')->value('id'));
});

it('never reduces or realigns more than is available', function () {
    $a = ($this->approved)('1000.00');
    BudgetReservation::factory()->create(['budget_allocation_id' => $a->id, 'amount' => '400.00']);

    expect(fn () => $this->adjust->reduce($this->c->budget, $a, '600.01', 'x', 'y', 'k1'))->toThrow(WorkflowViolation::class, '600.00');
    $this->adjust->reduce($this->c->budget, $a, '600.00', 'x', 'y', 'k2');
    expect($this->ledger->balance($a)->available())->toBe(0);
});

it('counts disbursements against obligations only, never twice against the allotment', function () {
    $a = ($this->approved)('1000.00');
    $obligation = BudgetObligation::factory()->create(['budget_allocation_id' => $a->id, 'amount' => '700.00']);
    BudgetDisbursement::factory()->create(['budget_obligation_id' => $obligation->id, 'amount' => '300.00']);

    $balance = $this->ledger->balance($a);
    expect(Money::fromCents($balance->available()))->toBe('300.00')
        ->and(Money::fromCents($balance->unpaidObligations()))->toBe('400.00');
});

it('treats a replayed request as the same adjustment', function () {
    $a = ($this->approved)();
    $first = $this->adjust->augment($this->c->budget, $a, '100.00', 'x', 'y', 'same-key');
    $again = $this->adjust->augment($this->c->budget, $a, '100.00', 'x', 'y', 'same-key');

    expect($again->id)->toBe($first->id)
        ->and(fn () => $this->adjust->augment($this->c->budget, $a, '999.00', 'x', 'y', 'same-key'))->toThrow(WorkflowViolation::class, 'idempotency key');
});

it('does not adjust draft allotments or other fiscal years', function () {
    expect(fn () => $this->adjust->augment($this->c->budget, ($this->create)(), '1.00', 'x', 'y', 'k'))->toThrow(WorkflowViolation::class, 'not approved');

    $other = BudgetAllocation::factory()->create(['agency_id' => $this->c->agency->id, 'status' => 'approved']);
    expect(fn () => $this->adjust->realign($this->c->budget, ($this->approved)(), $other, '1.00', 'x', 'y', 'k2'))->toThrow(WorkflowViolation::class, 'same fiscal year');
});

it('keeps adjustments append-only', function () {
    $adjustment = $this->adjust->augment($this->c->budget, ($this->approved)(), '1.00', 'x', 'y', 'k');

    expect(fn () => DB::table('budget_adjustments')->where('id', $adjustment->id)->update(['amount' => '9.00']))->toThrow(QueryException::class);
});

it('rejects amounts with more than two decimals', function () {
    $this->adjust->augment($this->c->budget, ($this->approved)(), '1.005', 'x', 'y', 'k');
})->throws(InvalidArgumentException::class);
