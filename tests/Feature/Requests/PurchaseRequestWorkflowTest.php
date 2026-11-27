<?php

use App\Domain\Budget\AdjustBudget;
use App\Domain\Budget\BudgetLedger;
use App\Domain\Compliance\ComplianceFlags;
use App\Domain\Planning\ReviseApp;
use App\Domain\Requests\CreatePurchaseRequest;
use App\Domain\Requests\PurchaseRequestWorkflow;
use App\Domain\Shared\Money;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\PurchaseRequestStatus;
use App\Models\ApprovalStep;
use App\Models\BudgetAllocation;
use App\Models\BudgetReservation;
use App\Models\ComplianceFlag;
use App\Models\StatusHistory;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->c = requestContext();
    $this->flow = app(PurchaseRequestWorkflow::class);
    $this->available = fn (?BudgetAllocation $a = null) => Money::fromCents(app(BudgetLedger::class)->balance(($a ?? $this->c->allocation)->fresh())->available());
    $this->toBudget = function () {
        $pr = draftRequest($this->c);
        $this->flow->submit($this->c->requester, $pr);
        $this->flow->approve($this->c->head, $pr);

        return $pr->fresh();
    };
});

it('numbers requests and computes line totals exactly', function () {
    $pr = draftRequest($this->c, '3', '33.3333');
    $this->flow->submit($this->c->requester, $pr);

    expect($pr->reference_no)->toBe('PR-2027-0001')
        ->and($pr->items()->sole()->estimated_total)->toBe('100.00')
        ->and($pr->fresh()->total_amount)->toBe('100.00');
});

it('requires purchase_request.create for the department', function () {
    app(CreatePurchaseRequest::class)($this->c->head, $this->c->dept, $this->c->fy, 'x');
})->throws(WorkflowViolation::class, 'purchase_request.create');

it('runs submit, department approval and budget certification with a reservation', function () {
    $pr = draftRequest($this->c);
    $this->flow->submit($this->c->requester, $pr);
    expect($pr->fresh()->status)->toBe(PurchaseRequestStatus::DepartmentReview);

    $this->flow->approve($this->c->head, $pr);
    expect($pr->fresh()->status)->toBe(PurchaseRequestStatus::BudgetReview);

    $this->flow->certifyFunds($this->c->budget, $pr, $this->c->allocation);
    $pr->refresh();

    expect($pr->status)->toBe(PurchaseRequestStatus::Approved)
        ->and($pr->approved_by)->toBe($this->c->budget->id)
        ->and($pr->activeReservation()->amount)->toBe('25000.00')
        ->and(($this->available)())->toBe('975000.00')
        ->and(StatusHistory::where('subject_id', $pr->id)->where('subject_type', $pr->getMorphClass())->pluck('to_status')->all())
        ->toBe(['submitted', 'department_review', 'budget_review', 'approved']);
});

it('will not certify through the plain approval action, nor approve at the budget step without funds', function () {
    $pr = ($this->toBudget)();

    expect(fn () => $this->flow->approve($this->c->budget, $pr))->toThrow(WorkflowViolation::class, 'certifies funds');
});

it('refuses certification beyond the available allotment and leaves no trace', function () {
    $pr = draftRequest($this->c, '400', '250.00');
    $this->c->allocation->refresh();
    app(AdjustBudget::class)->reduce($this->c->budget, $this->c->allocation, '950000.00', 'DBM', 'Savings', 'k1');
    $this->flow->submit($this->c->requester, $pr);
    $this->flow->approve($this->c->head, $pr);

    expect(fn () => $this->flow->certifyFunds($this->c->budget, $pr, $this->c->allocation))->toThrow(WorkflowViolation::class, 'Insufficient funds');
    expect($pr->fresh()->status)->toBe(PurchaseRequestStatus::BudgetReview)
        ->and($pr->approvalActions()->count())->toBe(1)
        ->and($pr->reservations()->count())->toBe(0);
});

it('checks the allotment matches the request', function () {
    $pr = ($this->toBudget)();
    $other = BudgetAllocation::factory()->create(['agency_id' => $this->c->agency->id, 'status' => 'approved']);

    $this->flow->certifyFunds($this->c->budget, $pr, $other);
})->throws(WorkflowViolation::class, 'another fiscal year');

it('blocks requests not covered by the approved APP unless a plan exception is documented', function () {
    $pr = draftRequest($this->c, linked: false);
    expect(fn () => $this->flow->submit($this->c->requester, $pr))->toThrow(WorkflowViolation::class, 'approved APP');

    $pr->update(['plan_exception_basis' => 'Emergency procurement, IRR Sec. X', 'plan_exception_reason' => 'Typhoon damage']);
    $this->flow->submit($this->c->requester, $pr);
    $this->flow->approve($this->c->head, $pr);

    expect(fn () => $this->flow->certifyFunds($this->c->budget, $pr, $this->c->allocation))->toThrow(WorkflowViolation::class, 'compliance flag');

    app(ComplianceFlags::class)->review($this->c->hope, ComplianceFlag::where('flag_type', 'unplanned_procurement')->sole(), 'reviewed', 'Emergency basis verified');
    $this->flow->certifyFunds($this->c->budget, $pr, $this->c->allocation);
    expect($pr->fresh()->status)->toBe(PurchaseRequestStatus::Approved);
});

it('rejects APP items that are not part of the approved APP', function () {
    $revision = app(ReviseApp::class)($this->c->appPlanner, $this->c->appItem->appVersion->app, 'Revision');
    $draftItem = $revision->items()->sole();
    $pr = app(CreatePurchaseRequest::class)($this->c->requester, $this->c->dept, $this->c->fy, 'x', $draftItem);
    $pr->items()->create(['description' => 'x', 'unit_of_measure_id' => $this->c->uom->id, 'quantity' => 1, 'estimated_unit_cost' => '1']);

    $this->flow->submit($this->c->requester, $pr);
})->throws(WorkflowViolation::class, 'not part of the currently approved APP');

it('keeps total requests within the APP item budget', function () {
    $this->flow->submit($this->c->requester, draftRequest($this->c, '300', '250.00'));  // 75,000 of 100,000

    expect(fn () => $this->flow->submit($this->c->requester, draftRequest($this->c, '101', '250.00')))->toThrow(WorkflowViolation::class, '25,000.00')
        ->and($this->flow->submit($this->c->requester, draftRequest($this->c, '100', '250.00'))->status)->toBe(PurchaseRequestStatus::DepartmentReview);
});

it('frees APP item budget when a request is cancelled', function () {
    $first = draftRequest($this->c, '400', '250.00');
    $this->flow->submit($this->c->requester, $first);
    expect(fn () => $this->flow->submit($this->c->requester, draftRequest($this->c, '1', '1.00')))->toThrow(WorkflowViolation::class);

    $this->flow->cancel($this->c->requester, $first, 'Requirement changed');
    expect($this->flow->submit($this->c->requester, draftRequest($this->c, '1', '1.00'))->exists)->toBeTrue();
});

it('returns for correction, freezes while under review and accepts resubmission', function () {
    $pr = draftRequest($this->c);
    $this->flow->submit($this->c->requester, $pr);

    expect(fn () => $pr->items()->first()->update(['quantity' => 5]))->toThrow(LogicException::class)
        ->and(fn () => $pr->fresh()->update(['purpose' => 'changed']))->toThrow(LogicException::class);

    $this->flow->returnForCorrection($this->c->head, $pr, 'Specify paper weight');
    expect($pr->fresh()->status)->toBe(PurchaseRequestStatus::Returned);

    $pr->items()->first()->update(['description' => 'Bond paper, A4, 80gsm']);
    $this->flow->submit($this->c->requester, $pr->fresh());
    $this->flow->approve($this->c->head, $pr);
    $this->flow->certifyFunds($this->c->budget, $pr, $this->c->allocation);
    expect($pr->fresh()->status)->toBe(PurchaseRequestStatus::Approved);
});

it('releases the reservation when an approved request is cancelled', function () {
    $pr = ($this->toBudget)();
    $this->flow->certifyFunds($this->c->budget, $pr, $this->c->allocation);
    expect(($this->available)())->toBe('975000.00');

    $this->flow->cancel($this->c->requester, $pr->fresh(), 'Project deferred');

    expect($pr->fresh()->status)->toBe(PurchaseRequestStatus::Cancelled)
        ->and($pr->reservations()->sole()->status)->toBe('released')
        ->and(($this->available)())->toBe('1000000.00');
});

it('treats rejection as final', function () {
    $pr = ($this->toBudget)();
    $this->flow->reject($this->c->budget, $pr, 'Not a priority');

    expect($pr->fresh()->status)->toBe(PurchaseRequestStatus::Rejected)
        ->and(fn () => $this->flow->submit($this->c->requester, $pr->fresh()))->toThrow(WorkflowViolation::class);
});

it('does not let the requester approve or certify their own request', function () {
    grant($this->c->requester, 'department_head', $this->c->dept);
    $pr = draftRequest($this->c);
    $this->flow->submit($this->c->requester, $pr);

    $this->flow->approve($this->c->requester, $pr);
})->throws(WorkflowViolation::class, 'initiator');

it('requires a budget certification step in the workflow', function () {
    ApprovalStep::query()->where('permission_code', 'budget.certify')->update(['is_required' => false]);

    $this->flow->submit($this->c->requester, draftRequest($this->c));
})->throws(WorkflowViolation::class, 'budget certification step');

it('keeps reservations immutable and one active per request', function () {
    $pr = ($this->toBudget)();
    $this->flow->certifyFunds($this->c->budget, $pr, $this->c->allocation);
    $reservation = $pr->activeReservation();

    expect(fn () => $reservation->update(['amount' => '1.00']))->toThrow(LogicException::class)
        ->and(fn () => BudgetReservation::factory()->create(['purchase_request_id' => $pr->id, 'budget_allocation_id' => $this->c->allocation->id]))
        ->toThrow(QueryException::class);
});
