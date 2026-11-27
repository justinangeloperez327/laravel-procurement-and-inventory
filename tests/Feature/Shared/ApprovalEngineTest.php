<?php

use App\Domain\Approvals\ApprovalEngine;
use App\Domain\Approvals\ApprovalOutcome;
use App\Domain\Identity\DelegatePermission;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\ApprovalDecision;
use App\Models\Agency;
use App\Models\ApprovalWorkflow;
use App\Models\Department;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    seedRoles();
    $this->agency = Agency::factory()->create();
    $this->dept = Department::factory()->for($this->agency)->create();
    $this->requester = User::factory()->forAgency($this->agency)->create();
    $this->pr = PurchaseRequest::factory()->create([
        'agency_id' => $this->agency->id, 'department_id' => $this->dept->id, 'requested_by' => $this->requester->id,
    ]);

    $workflow = ApprovalWorkflow::factory()->create(['agency_id' => $this->agency->id, 'subject_type' => 'purchase_request']);
    $workflow->steps()->create(['sequence' => 1, 'name' => 'Department head', 'permission_code' => 'purchase_request.approve', 'department_scoped' => true, 'is_required' => true]);
    $workflow->steps()->create(['sequence' => 2, 'name' => 'Budget', 'permission_code' => 'budget.certify', 'department_scoped' => false, 'is_required' => true]);

    $this->head = User::factory()->forAgency($this->agency)->create();
    grant($this->head, 'department_head', $this->dept);
    $this->budget = User::factory()->forAgency($this->agency)->create();
    grant($this->budget, 'budget_officer');
    $this->engine = app(ApprovalEngine::class);
});

it('walks the required steps in order', function () {
    expect($this->engine->state($this->pr)->nextStep->name)->toBe('Department head');

    $state = $this->engine->act($this->head, $this->pr, ApprovalDecision::Approved);
    expect($state->outcome)->toBe(ApprovalOutcome::Pending)->and($state->nextStep->name)->toBe('Budget');

    $state = $this->engine->act($this->budget, $this->pr, ApprovalDecision::Approved);
    expect($state->outcome)->toBe(ApprovalOutcome::Approved)->and($this->pr->approvalActions)->toHaveCount(2);
});

it('requires the step permission in the right scope', function () {
    $this->engine->act($this->budget, $this->pr, ApprovalDecision::Approved);
})->throws(WorkflowViolation::class, 'do not hold purchase_request.approve');

it('denies a head of another department', function () {
    $otherHead = User::factory()->forAgency($this->agency)->create();
    grant($otherHead, 'department_head', Department::factory()->for($this->agency)->create());

    $this->engine->act($otherHead, $this->pr, ApprovalDecision::Approved);
})->throws(WorkflowViolation::class, 'do not hold');

it('never lets the initiator approve their own record', function () {
    grant($this->requester, 'department_head', $this->dept);

    $this->engine->act($this->requester, $this->pr, ApprovalDecision::Approved);
})->throws(WorkflowViolation::class, 'initiator');

it('does not let one person decide two steps', function () {
    $both = User::factory()->forAgency($this->agency)->create();
    grant($both, 'department_head', $this->dept);
    grant($both, 'budget_officer');

    $this->engine->act($both, $this->pr, ApprovalDecision::Approved);
    $this->engine->act($both, $this->pr, ApprovalDecision::Approved);
})->throws(WorkflowViolation::class, 'more than one step');

it('records delegated authority and the principal', function () {
    $oic = User::factory()->forAgency($this->agency)->create();
    $delegation = app(DelegatePermission::class)(
        $this->head, $oic, Permission::where('code', 'purchase_request.approve')->sole(),
        CarbonImmutable::now(), CarbonImmutable::now()->addWeek(), 'Leave', $this->dept,
    );

    $this->engine->act($oic, $this->pr, ApprovalDecision::Approved);
    $action = $this->pr->approvalActions()->sole();

    expect($action->delegation_id)->toBe($delegation->id)->and($action->on_behalf_of_id)->toBe($this->head->id);
});

it('stops the principal from also deciding a later step after their delegate acted', function () {
    $oic = User::factory()->forAgency($this->agency)->create();
    app(DelegatePermission::class)(
        $this->head, $oic, Permission::where('code', 'purchase_request.approve')->sole(),
        CarbonImmutable::now(), CarbonImmutable::now()->addWeek(), 'Leave', $this->dept,
    );
    grant($this->head, 'budget_officer');

    $this->engine->act($oic, $this->pr, ApprovalDecision::Approved);
    $this->engine->act($this->head, $this->pr, ApprovalDecision::Approved);
})->throws(WorkflowViolation::class, 'more than one step');

it('restarts the round after a return', function () {
    $this->engine->act($this->head, $this->pr, ApprovalDecision::Approved);
    $state = $this->engine->act($this->budget, $this->pr, ApprovalDecision::Returned, 'Attach the canvass');

    expect($state->outcome)->toBe(ApprovalOutcome::Returned)->and($state->nextStep->name)->toBe('Department head');

    $this->engine->act($this->head, $this->pr, ApprovalDecision::Approved);
    expect($this->engine->act($this->budget, $this->pr, ApprovalDecision::Approved)->outcome)->toBe(ApprovalOutcome::Approved);
});

it('treats rejection as final and requires remarks', function () {
    expect(fn () => $this->engine->act($this->head, $this->pr, ApprovalDecision::Rejected))->toThrow(WorkflowViolation::class, 'Remarks');

    $state = $this->engine->act($this->head, $this->pr, ApprovalDecision::Rejected, 'Not in the APP');
    expect($state->outcome)->toBe(ApprovalOutcome::Rejected)
        ->and(fn () => $this->engine->act($this->budget, $this->pr, ApprovalDecision::Approved))->toThrow(WorkflowViolation::class, 'already rejected');
});

it('fails clearly when no workflow is configured', function () {
    ApprovalWorkflow::query()->update(['is_active' => false]);

    $this->engine->state($this->pr);
})->throws(WorkflowViolation::class, 'No active approval workflow');

it('uses the workflow version in effect', function () {
    $newer = ApprovalWorkflow::factory()->create([
        'agency_id' => $this->agency->id, 'subject_type' => 'purchase_request', 'effective_from' => now()->subDay(), 'name' => 'Revised',
    ]);
    $newer->steps()->create(['sequence' => 1, 'name' => 'Budget only', 'permission_code' => 'budget.certify', 'department_scoped' => false, 'is_required' => true]);

    expect($this->engine->state($this->pr)->workflow->is($newer))->toBeTrue();
});
