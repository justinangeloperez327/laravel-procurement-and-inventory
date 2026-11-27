<?php

use App\Domain\Compliance\ComplianceFlags;
use App\Domain\Compliance\ConflictDeclarations;
use App\Domain\Shared\WorkflowViolation;
use App\Domain\Suppliers\SupplierComplianceSweep;
use App\Domain\Suppliers\SupplierEligibility;
use App\Domain\Suppliers\SupplierSanctions;
use App\Enums\SanctionType;
use App\Enums\SupplierStatus;
use App\Models\ComplianceFlag;
use App\Models\ProcurementProject;
use App\Models\User;

beforeEach(function () {
    $this->c = supplierContext();
    $this->sanctions = app(SupplierSanctions::class);
    $this->eligibility = app(SupplierEligibility::class);
    $this->flags = app(ComplianceFlags::class);
    $this->supplier = eligibleSupplier($this->c);
    $this->sanction = fn (SanctionType $type = SanctionType::Suspension, ?string $from = null, ?string $to = null) => $this->sanctions->record(
        $this->c->officer, $this->supplier, $type, 'GPPB', 'GPPB-Res-01-2027', 'Failure to perform', $from ?? now()->toDateString(), $to,
    );
});

it('makes a sanctioned supplier ineligible and updates its status', function () {
    ($this->sanction)(SanctionType::Blacklisting, now()->toDateString(), now()->addYear()->toDateString());

    expect($this->supplier->fresh()->status)->toBe(SupplierStatus::Blacklisted)
        ->and($this->eligibility->check($this->supplier)->reasons[0])->toStartWith('Blacklisting under GPPB-Res-01-2027')
        ->and(fn () => $this->eligibility->assert($this->supplier))->toThrow(WorkflowViolation::class, 'not eligible');
});

it('raises a blocking flag that stays until reviewed, even after the sanction is lifted', function () {
    $sanction = $this->sanctions->record($this->c->hope, $this->supplier, SanctionType::Suspension, 'GPPB', 'GPPB-Res-01-2027', 'Failure to perform', now()->toDateString());
    $this->sanctions->lift($this->c->officer, $sanction, 'Lifted by GPPB Res. 05-2027');

    expect($this->supplier->fresh()->status)->toBe(SupplierStatus::Active)
        ->and($this->eligibility->check($this->supplier)->reasons)->toBe(['open blocking compliance flag (sanctioned supplier)']);

    $flag = ComplianceFlag::where('flag_type', 'sanctioned_supplier')->sole();
    expect(fn () => $this->flags->review($this->c->officer, $flag, 'reviewed', 'ok'))->toThrow(WorkflowViolation::class, 'compliance.review')
        ->and(fn () => $this->flags->review($this->c->hope, $flag, 'reviewed', 'ok'))->toThrow(WorkflowViolation::class, 'raised a flag');

    $chair = User::factory()->forAgency($this->c->agency)->create();
    grant($chair, 'bac_chair');
    $this->flags->review($chair, $flag, 'reviewed', 'Sanction lifted; supplier may participate again.');
    expect($this->eligibility->check($this->supplier)->eligible())->toBeTrue();
});

it('applies future-dated sanctions on their start date', function () {
    ($this->sanction)(SanctionType::Suspension, now()->addDays(10)->toDateString(), now()->addDays(40)->toDateString());

    expect($this->supplier->fresh()->status)->toBe(SupplierStatus::Active)
        ->and($this->eligibility->check($this->supplier, now()->addDays(10))->reasons[0])->toStartWith('Suspension')
        ->and($this->eligibility->check($this->supplier, now()->addDays(40))->reasons)->not->toContain(fn ($r) => str_starts_with($r, 'Suspension'));

    $this->travel(10)->days();
    app(SupplierComplianceSweep::class)->run();
    expect($this->supplier->fresh()->status)->toBe(SupplierStatus::Suspended);

    $this->travel(31)->days();
    app(SupplierComplianceSweep::class)->run();
    expect($this->supplier->fresh()->status)->toBe(SupplierStatus::Active);
});

it('never edits or deletes sanctions', function () {
    $sanction = ($this->sanction)();

    expect(fn () => $sanction->update(['reason' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $sanction->delete())->toThrow(LogicException::class);
});

it('requires supplier.sanction', function () {
    $this->sanctions->record($this->c->registrar, $this->supplier, SanctionType::Suspension, 'GPPB', 'X', 'Y', now()->toDateString());
})->throws(WorkflowViolation::class, 'supplier.sanction');

it('flags expiring and expired documents once, without duplicates', function () {
    $sweep = app(SupplierComplianceSweep::class);
    $expiry = $this->supplier->documents()->where('document_type', 'tax_clearance')->value('expires_on');

    $this->travelTo($expiry->copy()->subDays(10));
    $sweep->run();
    $sweep->run();
    expect(ComplianceFlag::where('flag_type', 'expiring_document:tax_clearance')->count())->toBe(1);

    $this->travelTo($expiry->copy()->addDay());
    $sweep->run();
    expect(ComplianceFlag::where('flag_type', 'expired_document:tax_clearance')->sole()->severity)->toBe('warning')
        ->and($this->eligibility->check($this->supplier)->eligible())->toBeFalse();
});

it('records conflict declarations and tells evaluators to recuse', function () {
    $evaluator = User::factory()->forAgency($this->c->agency)->create();
    grant($evaluator, 'bac_chair');
    $project = ProcurementProject::factory()->create(['agency_id' => $this->c->agency->id]);
    $declarations = app(ConflictDeclarations::class);

    expect(ConflictDeclarations::hasDeclared($evaluator, $project))->toBeFalse();
    $declarations->declare($evaluator, $project, null, false);
    expect(ConflictDeclarations::mustRecuse($evaluator, $project))->toBeFalse();

    $this->travel(1)->minutes();
    expect(fn () => $declarations->declare($evaluator, $project, $this->supplier, true))->toThrow(WorkflowViolation::class, 'Describe');
    $d = $declarations->declare($evaluator, $project, $this->supplier, true, 'Sibling owns the supplier', 'recused');

    expect(ConflictDeclarations::mustRecuse($evaluator, $project))->toBeTrue()
        ->and(ConflictDeclarations::mustRecuse($evaluator, $this->supplier))->toBeTrue()
        ->and(ComplianceFlag::where('flag_type', 'conflict_of_interest')->sole()->severity)->toBe('warning');

    expect(fn () => $declarations->review($evaluator, $d, 'self'))->toThrow(WorkflowViolation::class, 'own declaration');
    $declarations->review($this->c->hope, $d, 'Recusal noted');
    expect($d->fresh()->reviewed_by)->toBe($this->c->hope->id);
});

it('makes an unrecused conflict blocking', function () {
    $user = User::factory()->forAgency($this->c->agency)->create();
    app(ConflictDeclarations::class)->declare($user, null, $this->supplier, true, 'Shareholder', 'disclosed_only');

    expect(ComplianceFlag::where('flag_type', 'conflict_of_interest')->sole()->severity)->toBe('blocking');
});
