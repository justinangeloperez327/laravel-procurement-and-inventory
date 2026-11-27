<?php

use App\Domain\Identity\RevokeRole;
use App\Models\Agency;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    seedRoles();
    $this->agency = Agency::factory()->create();
    $this->dept = Department::factory()->for($this->agency)->create();
    $this->otherDept = Department::factory()->for($this->agency)->create();
});

it('denies permissions to users without roles', function () {
    $user = User::factory()->forAgency($this->agency)->create();

    expect($user->hasPermission('purchase_request.submit', $this->dept))->toBeFalse();
});

it('never derives statutory authority from technical administrator status', function () {
    $admin = adminFor($this->agency);

    expect($admin->hasPermission('award.approve'))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('app.approve'))->toBeFalse();
});

it('limits department-scoped roles to their department', function () {
    $user = User::factory()->forAgency($this->agency)->create();
    grant($user, 'end_user', $this->dept);

    expect($user->hasPermission('purchase_request.submit', $this->dept))->toBeTrue()
        ->and($user->hasPermission('purchase_request.submit', $this->otherDept))->toBeFalse()
        ->and($user->hasPermission('purchase_request.submit'))->toBeFalse('a department grant is not agency-wide');
});

it('lets agency-wide roles cover every department of the agency only', function () {
    $user = User::factory()->forAgency($this->agency)->create();
    grant($user, 'budget_officer');
    $foreignDept = Department::factory()->create();

    expect($user->hasPermission('budget.certify'))->toBeTrue()
        ->and($user->hasPermission('budget.certify', $this->dept))->toBeTrue()
        ->and($user->hasPermission('budget.certify', $foreignDept))->toBeFalse();
});

it('resolves permission strings through the gate', function () {
    $user = User::factory()->forAgency($this->agency)->create();
    grant($user, 'end_user', $this->dept);

    expect(Gate::forUser($user)->allows('purchase_request.submit', $this->dept))->toBeTrue()
        ->and(Gate::forUser($user)->allows('purchase_request.submit', $this->otherDept))->toBeFalse();
});

it('honours expiry and revocation', function () {
    $user = User::factory()->forAgency($this->agency)->create();
    $assignment = grant($user, 'budget_officer', null, now()->addDay());

    expect($user->hasPermission('budget.certify'))->toBeTrue();

    $this->travel(2)->days();
    expect($user->hasPermission('budget.certify'))->toBeFalse();
    $this->travelBack();

    app(RevokeRole::class)(adminFor($this->agency), $assignment, 'Reassigned');
    expect($user->hasPermission('budget.certify'))->toBeFalse();
});

it('strips all authority from deactivated users', function () {
    $user = User::factory()->forAgency($this->agency)->create();
    grant($user, 'budget_officer');
    $user->update(['is_active' => false]);

    expect($user->fresh()->hasPermission('budget.certify'))->toBeFalse();
});
