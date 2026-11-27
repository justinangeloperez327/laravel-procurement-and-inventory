<?php

use App\Domain\Compliance\ComplianceFlags;
use App\Domain\Suppliers\ManageSupplierStatus;
use App\Domain\Suppliers\RegisterSupplier;
use App\Enums\SupplierStatus;
use App\Filament\Resources\ComplianceFlags\Pages\ListComplianceFlags;
use App\Filament\Resources\ConflictDeclarations\Pages\ListConflictDeclarations;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\ViewSupplier;
use App\Filament\Resources\Suppliers\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Suppliers\RelationManagers\SanctionsRelationManager;
use App\Models\ComplianceFlag;
use App\Models\ConflictDeclaration;
use App\Models\Supplier;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->c = supplierContext();
});

it('renders supplier and compliance pages for the right people', function () {
    $supplier = eligibleSupplier($this->c);

    $this->actingAs($this->c->registrar);
    $this->get('/admin/suppliers')->assertOk();
    $this->get('/admin/suppliers/create')->assertOk();
    $this->get("/admin/suppliers/{$supplier->id}")->assertOk()->assertSee('Eligible to participate');
    $this->get("/admin/suppliers/{$supplier->id}/edit")->assertOk();
    $this->get('/admin/conflict-declarations')->assertOk();
    $this->get('/admin/compliance-flags')->assertForbidden();

    $this->actingAs($this->c->hope);
    $this->get('/admin/compliance-flags')->assertOk();
    $this->get('/admin/suppliers/create')->assertForbidden();
});

it('registers a supplier from the form as pending', function () {
    $this->actingAs($this->c->registrar);

    Livewire::test(CreateSupplier::class)
        ->fillForm(['name' => 'Bayanihan Trading', 'tin' => '111-222-333-000', 'business_type' => 'sole_proprietorship'])
        ->call('create')
        ->assertHasNoFormErrors();

    $supplier = Supplier::sole();
    expect($supplier->status)->toBe(SupplierStatus::Pending)->and($supplier->tin)->toBe('111222333000');
    $this->get("/admin/suppliers/{$supplier->id}")->assertSee('registration has not been verified');
});

it('uploads a document, then lets a different person verify it', function () {
    $supplier = app(RegisterSupplier::class)($this->c->registrar, ['name' => 'X Corp', 'business_type' => 'corporation']);
    $this->actingAs($this->c->registrar);

    $rm = fn () => Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $supplier, 'pageClass' => ViewSupplier::class]);
    $rm()->callAction(TestAction::make('add')->table(), [
        'document_type' => 'tax_clearance',
        'reference_no' => 'TC-2027-1',
        'expires_on' => now()->addYear()->toDateString(),
        'file' => UploadedFile::fake()->create('clearance.pdf', 20, 'application/pdf'),
    ])->assertNotified('Document recorded');

    $doc = $supplier->documents()->sole();
    $rm()->assertActionHidden(TestAction::make('verify')->table($doc));

    $this->actingAs($this->c->verifier);
    $rm()->callAction(TestAction::make('verify')->table($doc))->assertNotified('Document verified');
    expect($doc->fresh()->verified_by)->toBe($this->c->verifier->id);
});

it('activates a fully documented supplier from the view page', function () {
    $supplier = eligibleSupplier($this->c);
    app(ManageSupplierStatus::class)->deactivate($this->c->registrar, $supplier, 'Test');
    app(ManageSupplierStatus::class)->reopen($this->c->registrar, $supplier, 'Test');

    $this->actingAs($this->c->verifier);
    Livewire::test(ViewSupplier::class, ['record' => $supplier->id])->callAction('activate')->assertNotified('Supplier activated');

    expect($supplier->fresh()->status)->toBe(SupplierStatus::Active);
});

it('records a sanction and shows the supplier as ineligible', function () {
    $supplier = eligibleSupplier($this->c);
    $this->actingAs($this->c->officer);

    Livewire::test(SanctionsRelationManager::class, ['ownerRecord' => $supplier, 'pageClass' => ViewSupplier::class])
        ->callAction(TestAction::make('record')->table(), [
            'sanction_type' => 'suspension', 'issuing_authority' => 'HoPE', 'order_reference' => 'HoPE-Order-7',
            'effective_from' => now()->toDateString(), 'reason' => 'Delayed delivery beyond 10%',
        ])
        ->assertNotified('Sanction recorded');

    expect($supplier->fresh()->status)->toBe(SupplierStatus::Suspended);
    $this->get("/admin/suppliers/{$supplier->id}")->assertSee('Suspension under HoPE-Order-7');
});

it('reviews compliance flags from the queue', function () {
    $supplier = eligibleSupplier($this->c);
    app(ComplianceFlags::class)->raise($this->c->agency->id, $supplier, 'possible_splitting', 'warning', 'Three related SVPs');
    $flag = ComplianceFlag::sole();

    $this->actingAs($this->c->hope);
    Livewire::test(ListComplianceFlags::class)
        ->assertCanSeeTableRecords([$flag])
        ->callAction(TestAction::make('review')->table($flag), ['decision' => 'dismissed', 'notes' => 'Different requirements and end users'])
        ->assertNotified('Review recorded');

    expect($flag->fresh()->status)->toBe('dismissed');
});

it('lets anyone declare, and shows each user only their own declarations', function () {
    $supplier = eligibleSupplier($this->c);
    $member = User::factory()->forAgency($this->c->agency)->create();
    grant($member, 'twg_member');

    $this->actingAs($member);
    Livewire::test(ListConflictDeclarations::class)
        ->callAction('declare', ['supplier_id' => $supplier->id, 'has_conflict' => true, 'nature' => 'Former employee', 'action_taken' => 'recused'])
        ->assertNotified('Declaration recorded');

    $other = User::factory()->forAgency($this->c->agency)->create();
    grant($other, 'twg_member');
    $this->actingAs($other);
    Livewire::test(ListConflictDeclarations::class)->assertCanNotSeeTableRecords(ConflictDeclaration::all());

    $this->actingAs($this->c->hope);
    Livewire::test(ListConflictDeclarations::class)->assertCanSeeTableRecords(ConflictDeclaration::all());
});
