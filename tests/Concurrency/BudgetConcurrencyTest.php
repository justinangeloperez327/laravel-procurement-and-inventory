<?php

use App\Domain\Budget\AdjustBudget;
use App\Domain\Budget\ApproveBudgetAllocation;
use App\Domain\Budget\BudgetLedger;
use App\Domain\Budget\CreateBudgetAllocation;
use App\Models\AccountCode;
use App\Models\FundSource;
use App\Models\User;

it('never lets concurrent reductions overdraw an allotment', function () {
    $c = planningContext();
    $allocation = app(ApproveBudgetAllocation::class)($c->budget2, app(CreateBudgetAllocation::class)(
        $c->budget, $c->fy, FundSource::factory()->create(['agency_id' => $c->agency->id]), AccountCode::factory()->create(), '1000.00', 'SARO',
    ));
    $officerId = $c->budget->id;

    $results = concurrently(6, function (int $i) use ($allocation, $officerId) {
        app(AdjustBudget::class)->reduce(User::find($officerId), $allocation, '300.00', 'x', 'y', "worker-{$i}");

        return 'ok';
    });

    $succeeded = count(array_filter($results, fn ($r) => $r === 'ok'));
    expect($succeeded)->toBe(3)
        ->and(app(BudgetLedger::class)->balance($allocation->fresh())->available())->toBe(10000);
});
