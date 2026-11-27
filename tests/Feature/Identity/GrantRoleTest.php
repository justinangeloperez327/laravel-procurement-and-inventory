<?php

use App\Domain\Identity\GrantRole;
use App\Domain\Identity\IdentityViolation;
use App\Domain\Identity\RevokeRole;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;

beforeEach(function () {
    seedRoles();
    $this->agency = Agency::factory()->create();
    $this->admin = adminFor($this->agency);
    $this->user = User::factory()->forAgency($this->agency)->create();
    $this->grant = app(GrantRole::class);
});

it('records who granted a role, when and why', function () {
    $assignment = ($this->grant)($this->admin, $this->user, role('end_user'), null, null, 'Office Order 12');

    expect($assignment->granted_by)->toBe($this->admin->id)
        ->and($assignment->reason)->toBe('Office Order 12')
        ->and(AuditLog::where('event', 'role.granted')->where('auditable_id', $assignment->id)->exists())->toBeTrue();
});

it('allows only active system administrators to grant', function () {
    $nonAdmin = User::factory()->forAgency($this->agency)->create();
    ($this->grant)($nonAdmin, $this->user, role('end_user'));
})->throws(IdentityViolation::class, 'Only an active system administrator');

it('forbids administrators from granting roles to themselves', function () {
    ($this->grant)($this->admin, $this->admin, role('hope'));
})->throws(IdentityViolation::class, 'themselves');

it('rejects segregation-of-duties conflicts', function () {
    ($this->grant)($this->admin, $this->user, role('bac_member'));
    ($this->grant)($this->admin, $this->user, role('hope'));
})->throws(IdentityViolation::class, 'incompatible');

it('allows a previously conflicting role once the conflicting grant is revoked', function () {
    $bac = ($this->grant)($this->admin, $this->user, role('bac_member'));
    app(RevokeRole::class)($this->admin, $bac, 'Term ended');

    expect(($this->grant)($this->admin, $this->user, role('hope'))->exists)->toBeTrue();
});

it('rejects duplicate grants in the same scope', function () {
    ($this->grant)($this->admin, $this->user, role('end_user'));
    ($this->grant)($this->admin, $this->user, role('end_user'));
})->throws(IdentityViolation::class, 'already holds');

it('rejects departments from another agency', function () {
    ($this->grant)($this->admin, $this->user, role('end_user'), Department::factory()->create());
})->throws(IdentityViolation::class, 'different agency');

it('rejects users without an agency and inactive users', function () {
    expect(fn () => ($this->grant)($this->admin, User::factory()->create(), role('end_user')))
        ->toThrow(IdentityViolation::class, 'must belong to an agency')
        ->and(fn () => ($this->grant)($this->admin, User::factory()->forAgency($this->agency)->inactive()->create(), role('end_user')))
        ->toThrow(IdentityViolation::class, 'inactive');
});

it('requires a reason and refuses double revocation', function () {
    $assignment = ($this->grant)($this->admin, $this->user, role('end_user'));

    expect(fn () => app(RevokeRole::class)($this->admin, $assignment, '  '))->toThrow(IdentityViolation::class, 'reason');

    app(RevokeRole::class)($this->admin, $assignment, 'Transferred');
    expect($assignment->fresh()->revoked_by)->toBe($this->admin->id)
        ->and(fn () => app(RevokeRole::class)($this->admin, $assignment, 'Again'))->toThrow(IdentityViolation::class, 'already revoked');
});
