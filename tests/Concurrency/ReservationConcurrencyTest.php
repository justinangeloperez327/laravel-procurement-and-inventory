<?php

use App\Domain\Budget\AdjustBudget;
use App\Domain\Budget\BudgetLedger;
use App\Domain\Requests\PurchaseRequestWorkflow;
use App\Models\BudgetAllocation;
use App\Models\ComplianceFlag;
use App\Models\PurchaseRequest;
use App\Models\User;

it('never lets concurrent budget certifications overcommit an allotment (docs/16)', function () {
    $c = requestContext();
    $flow = app(PurchaseRequestWorkflow::class);
    // Leave ₱60,000 available; six ₱25,000 requests compete for it.
    app(AdjustBudget::class)->reduce($c->budget, $c->allocation, '940000.00', 'DBM', 'Test', 'k');

    $prIds = [];
    foreach (range(1, 6) as $i) {
        $pr = draftRequest($c, '100', '250.00', linked: false);
        $pr->update(['plan_exception_basis' => 'Test', 'plan_exception_reason' => 'Concurrency test']);
        $flow->submit($c->requester, $pr);
        ComplianceFlag::query()->update(['status' => 'reviewed']);
        $flow->approve($c->head, $pr);
        $prIds[] = $pr->id;
    }
    $officerId = $c->budget->id;
    $allocationId = $c->allocation->id;

    $results = concurrently(6, function (int $i) use ($prIds, $officerId, $allocationId) {
        app(PurchaseRequestWorkflow::class)->certifyFunds(User::find($officerId), PurchaseRequest::find($prIds[$i]), BudgetAllocation::find($allocationId));

        return 'ok';
    });

    expect(count(array_filter($results, fn ($r) => $r === 'ok')))->toBe(2)
        ->and(app(BudgetLedger::class)->balance(BudgetAllocation::find($allocationId))->available())->toBe(1000000);
});
