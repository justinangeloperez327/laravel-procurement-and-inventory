<?php

use App\Domain\Identity\DelegatePermission;
use App\Domain\Identity\IdentityViolation;
use App\Domain\Identity\RevokeDelegation;
use App\Domain\Identity\RevokeRole;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Permission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

beforeEach(function () {
    seedRoles();
    $this->agency = Agency::factory()->create();
    $this->dept = Department::factory()->for($this->agency)->create();
    $this->head = User::factory()->forAgency($this->agency)->create();
    $this->headAssignment = grant($this->head, 'department_head', $this->dept);
    $this->oic = User::factory()->forAgency($this->agency)->create();
    $this->delegate = function (User $from, User $to, string $permission, ?Department $dept = null, int $days = 7) {
        $start = CarbonImmutable::now();
        $end = $days > 0 ? $start->addDays($days) : $start->subSecond();

        return app(DelegatePermission::class)(
            $from, $to, Permission::where('code', $permission)->firstOrFail(), $start, $end, 'On official travel', $dept,
        );
    };
});

it('grants time-boxed authority to the delegate', function () {
    ($this->delegate)($this->head, $this->oic, 'purchase_request.approve', $this->dept);

    expect($this->oic->hasPermission('purchase_request.approve', $this->dept))->toBeTrue()
        ->and(AuditLog::where('event', 'delegation.created')->exists())->toBeTrue();

    $this->travel(8)->days();
    expect($this->oic->hasPermission('purchase_request.approve', $this->dept))->toBeFalse();
});

it('lapses when the delegator loses the underlying authority', function () {
    ($this->delegate)($this->head, $this->oic, 'purchase_request.approve', $this->dept);
    app(RevokeRole::class)(adminFor($this->agency), $this->headAssignment, 'Retired');

    expect($this->oic->hasPermission('purchase_request.approve', $this->dept))->toBeFalse();
});

it('cannot delegate authority not held directly', function () {
    ($this->delegate)($this->head, $this->oic, 'award.approve');
})->throws(IdentityViolation::class, 'held directly');

it('cannot widen a department grant to agency-wide', function () {
    ($this->delegate)($this->head, $this->oic, 'purchase_request.approve');
})->throws(IdentityViolation::class, 'held directly');

it('cannot be re-delegated', function () {
    ($this->delegate)($this->head, $this->oic, 'purchase_request.approve', $this->dept);
    $third = User::factory()->forAgency($this->agency)->create();
    grant($this->oic, 'end_user', $this->dept);

    ($this->delegate)($this->oic, $third, 'purchase_request.approve', $this->dept);
})->throws(IdentityViolation::class, 'held directly');

it('rejects self-delegation, other agencies and non-positive windows', function () {
    expect(fn () => ($this->delegate)($this->head, $this->head, 'purchase_request.approve', $this->dept))
        ->toThrow(IdentityViolation::class, 'oneself')
        ->and(fn () => ($this->delegate)($this->head, User::factory()->forAgency(Agency::factory()->create())->create(), 'purchase_request.approve', $this->dept))
        ->toThrow(IdentityViolation::class, 'same agency')
        ->and(fn () => ($this->delegate)($this->head, $this->oic, 'purchase_request.approve', $this->dept, 0))
        ->toThrow(IdentityViolation::class, 'end after');
});

it('blocks delegations that would breach segregation of duties', function () {
    $hope = User::factory()->forAgency($this->agency)->create();
    grant($hope, 'hope');
    $bacMember = User::factory()->forAgency($this->agency)->create();
    grant($bacMember, 'bac_member');

    ($this->delegate)($hope, $bacMember, 'award.approve');
})->throws(IdentityViolation::class, 'incompatible');

it('can be revoked by the delegator with a reason', function () {
    $delegation = ($this->delegate)($this->head, $this->oic, 'purchase_request.approve', $this->dept);

    expect(fn () => app(RevokeDelegation::class)($this->oic, $delegation, 'No'))->toThrow(IdentityViolation::class, 'Only the delegator');

    app(RevokeDelegation::class)($this->head, $delegation, 'Returned early');

    expect($this->oic->hasPermission('purchase_request.approve', $this->dept))->toBeFalse()
        ->and(AuditLog::where('event', 'delegation.revoked')->exists())->toBeTrue();
});

it('is constrained in the database as well', function () {
    $delegation = ($this->delegate)($this->head, $this->oic, 'purchase_request.approve', $this->dept);

    expect(fn () => $delegation->forceFill(['ends_at' => $delegation->starts_at->subDay()])->save())
        ->toThrow(QueryException::class);
});
