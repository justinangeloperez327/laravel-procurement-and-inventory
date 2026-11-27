<?php

use App\Domain\Shared\HasTransitions;
use App\Domain\Shared\TransitionStatus;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\DeliveryStatus;
use App\Enums\PlanStatus;
use App\Enums\ProcurementProjectStatus;
use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\StatusHistory;
use App\Models\User;

beforeEach(function () {
    $this->transition = app(TransitionStatus::class);
    $this->pr = PurchaseRequest::factory()->create();
    $this->actor = User::factory()->create();
});

it('moves along allowed transitions and records history', function () {
    ($this->transition)($this->pr, PurchaseRequestStatus::Submitted, $this->actor);

    $history = StatusHistory::where('subject_id', $this->pr->id)->sole();
    expect($this->pr->status)->toBe(PurchaseRequestStatus::Submitted)
        ->and($this->pr->fresh()->status)->toBe(PurchaseRequestStatus::Submitted)
        ->and($history->from_status)->toBe('draft')
        ->and($history->to_status)->toBe('submitted')
        ->and($history->actor_id)->toBe($this->actor->id);
});

it('rejects transitions the state machine does not allow', function () {
    ($this->transition)($this->pr, PurchaseRequestStatus::Approved, $this->actor);
})->throws(WorkflowViolation::class, 'cannot move from draft to approved');

it('requires a reason to return, reject or cancel', function () {
    ($this->transition)($this->pr, PurchaseRequestStatus::Cancelled, $this->actor);
})->throws(WorkflowViolation::class, 'reason is required');

it('rejects a status from another state machine', function () {
    ($this->transition)($this->pr, PlanStatus::Submitted, $this->actor);
})->throws(WorkflowViolation::class);

it('uses the database state, not a stale in-memory copy', function () {
    $stale = PurchaseRequest::find($this->pr->id);
    ($this->transition)($this->pr, PurchaseRequestStatus::Submitted, $this->actor);

    // The stale copy still says draft, but draft -> submitted is no longer valid from submitted.
    ($this->transition)($stale, PurchaseRequestStatus::Submitted, $this->actor);
})->throws(WorkflowViolation::class, 'from submitted to submitted');

it('keeps terminal states terminal', function (HasTransitionsCase $case) {
    expect($case->status->allowedTransitions())->toBe([]);
})->with(fn () => array_map(fn ($s) => [new HasTransitionsCase($s)], [
    PurchaseRequestStatus::Fulfilled, PurchaseRequestStatus::Rejected, PurchaseRequestStatus::Cancelled,
    ProcurementProjectStatus::Completed, ProcurementProjectStatus::Cancelled, PlanStatus::Superseded, DeliveryStatus::Posted,
]));

it('only reaches award after a recommendation', function () {
    foreach (ProcurementProjectStatus::cases() as $status) {
        $reachesAward = in_array(ProcurementProjectStatus::Awarded, $status->allowedTransitions(), true);
        expect($reachesAward)->toBe($status === ProcurementProjectStatus::Recommended);
    }
});

final class HasTransitionsCase
{
    public function __construct(public HasTransitions $status) {}
}
