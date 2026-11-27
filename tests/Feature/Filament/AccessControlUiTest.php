<?php

use App\Filament\Resources\Agencies\Pages\ManageAgencies;
use App\Filament\Resources\Delegations\Pages\ManageDelegations;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\RoleAssignmentsRelationManager;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Permission;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    seedRoles();
    $this->agency = Agency::factory()->create();
    $this->dept = Department::factory()->for($this->agency)->create();
    $this->admin = adminFor($this->agency);
});

dataset('admin pages', [
    '/admin/agencies', '/admin/departments', '/admin/fiscal-years', '/admin/users',
    '/admin/users/create', '/admin/roles', '/admin/delegations', '/admin/audit-logs',
]);

it('renders every administration page for administrators', function (string $url) {
    $this->actingAs($this->admin)->get($url)->assertOk();
})->with('admin pages');

it('hides administration from users without authority', function (string $url) {
    $user = User::factory()->forAgency($this->agency)->create();
    grant($user, 'end_user', $this->dept);

    $response = $this->actingAs($user)->get($url);
    $url === '/admin/delegations' ? $response->assertOk() : $response->assertForbidden();
})->with('admin pages');

it('lets auditors read but not change master data or audit logs', function () {
    $auditor = User::factory()->forAgency($this->agency)->create();
    grant($auditor, 'auditor');
    $log = AuditLog::record('test.event');

    $this->actingAs($auditor);
    $this->get('/admin/agencies')->assertOk();
    $this->get('/admin/audit-logs')->assertOk();
    $this->get("/admin/audit-logs/{$log->id}")->assertOk();
    $this->get('/admin/users/create')->assertForbidden();

    Livewire::test(ManageAgencies::class)->assertActionHidden('create');
});

it('denies the panel to deactivated users', function () {
    $user = User::factory()->forAgency($this->agency)->create();
    grant($user, 'end_user', $this->dept);
    $user->update(['is_active' => false]);

    $this->actingAs($user->fresh())->get('/admin')->assertForbidden();
});

it('creates users without exposing the administrator flag', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Ana Cruz',
            'email' => 'ana@example.test',
            'password' => 'a-strong-passphrase',
            'agency_id' => $this->agency->id,
            'department_id' => $this->dept->id,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::where('email', 'ana@example.test')->sole();
    expect($created->is_admin)->toBeFalse()->and($created->department_id)->toBe($this->dept->id);
});

it('grants and revokes roles through the relation manager', function () {
    $user = User::factory()->forAgency($this->agency)->create();
    $this->actingAs($this->admin);

    $component = Livewire::test(RoleAssignmentsRelationManager::class, ['ownerRecord' => $user, 'pageClass' => EditUser::class])
        ->callAction(TestAction::make('grant')->table(), [
            'role_id' => role('end_user')->id,
            'department_id' => $this->dept->id,
            'reason' => 'Office Order 7',
        ])
        ->assertHasNoFormErrors();

    $assignment = $user->roleAssignments()->sole();
    expect($user->hasPermission('purchase_request.submit', $this->dept))->toBeTrue();

    $component->callAction(TestAction::make('revoke')->table($assignment), ['reason' => 'Transferred']);
    expect($assignment->fresh()->revoked_at)->not->toBeNull();
});

it('reports segregation conflicts instead of granting', function () {
    $user = User::factory()->forAgency($this->agency)->create();
    grant($user, 'bac_member');
    $this->actingAs($this->admin);

    Livewire::test(RoleAssignmentsRelationManager::class, ['ownerRecord' => $user, 'pageClass' => EditUser::class])
        ->callAction(TestAction::make('grant')->table(), ['role_id' => role('hope')->id, 'reason' => 'Designation'])
        ->assertNotified('Change not allowed');

    expect($user->roleAssignments()->count())->toBe(1);
});

it('does not let administrators grant roles to themselves in the UI', function () {
    $this->actingAs($this->admin);

    Livewire::test(RoleAssignmentsRelationManager::class, ['ownerRecord' => $this->admin, 'pageClass' => EditUser::class])
        ->assertActionHidden(TestAction::make('grant')->table());
});

it('lets authority holders delegate from the delegations page', function () {
    $head = User::factory()->forAgency($this->agency)->create();
    grant($head, 'department_head', $this->dept);
    $oic = User::factory()->forAgency($this->agency)->create();
    $this->actingAs($head);

    Livewire::test(ManageDelegations::class)
        ->callAction('delegate', [
            'permission_id' => Permission::where('code', 'purchase_request.approve')->value('id'),
            'delegate_id' => $oic->id,
            'department_id' => $this->dept->id,
            'starts_at' => now()->toDateTimeString(),
            'ends_at' => now()->addDays(5)->toDateTimeString(),
            'reason' => 'Leave, Office Order 3',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Authority delegated');

    expect($oic->hasPermission('purchase_request.approve', $this->dept))->toBeTrue();
});
