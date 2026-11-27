<?php

use App\Domain\Shared\WorkflowViolation;
use App\Domain\Suppliers\BeneficialOwnership;
use App\Domain\Suppliers\ManageSupplierStatus;
use App\Domain\Suppliers\RegisterSupplier;
use App\Domain\Suppliers\SupplierDocuments;
use App\Domain\Suppliers\SupplierEligibility;
use App\Enums\SupplierStatus;
use App\Models\Supplier;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->c = supplierContext();
    $this->docs = app(SupplierDocuments::class);
    $this->owners = app(BeneficialOwnership::class);
    $this->status = app(ManageSupplierStatus::class);
    $this->eligibility = app(SupplierEligibility::class);
    $this->register = fn (array $data = []) => app(RegisterSupplier::class)($this->c->registrar, array_merge(['name' => 'Acme Office Supply', 'tin' => '123-456-789-000', 'business_type' => 'corporation'], $data));
    $this->pdf = fn () => UploadedFile::fake()->createWithContent('doc.pdf', '%PDF-1.4 x');
});

it('registers suppliers as pending and normalizes the TIN', function () {
    $supplier = ($this->register)();

    expect($supplier->status)->toBe(SupplierStatus::Pending)
        ->and($supplier->tin)->toBe('123456789000')
        ->and(fn () => ($this->register)(['tin' => '123456789000']))->toThrow(WorkflowViolation::class, 'already registered')
        ->and(fn () => app(RegisterSupplier::class)($this->c->hope, ['name' => 'X', 'business_type' => 'corporation']))->toThrow(WorkflowViolation::class, 'supplier.manage');
});

it('explains every reason a new supplier is not eligible', function () {
    $reasons = $this->eligibility->check(($this->register)())->reasons;

    expect($reasons)->toContain('registration has not been verified')
        ->toContain('PhilGEPS registration certificate is missing, unverified or expired')
        ->toContain('no beneficial ownership declaration');
});

it('becomes eligible only after documents and ownership are verified and it is activated', function () {
    $supplier = eligibleSupplier($this->c);

    expect($supplier->status)->toBe(SupplierStatus::Active)
        ->and($this->eligibility->check($supplier)->eligible())->toBeTrue();
});

it('will not activate with unverified documents or ownership', function () {
    $supplier = ($this->register)();
    $this->docs->add($this->c->registrar, $supplier, 'tax_clearance', ($this->pdf)(), 'TC-1', null, now()->addYear()->toDateString());

    expect(fn () => $this->status->activate($this->c->verifier, $supplier))->toThrow(WorkflowViolation::class, 'Cannot activate');

    $this->owners->declare($this->c->registrar, $supplier, 'Ana', '60');
    expect(fn () => $this->status->activate($this->c->verifier, $supplier))->toThrow(WorkflowViolation::class, 'beneficial ownership');
});

it('separates recording from verification', function () {
    $supplier = ($this->register)();
    $doc = $this->docs->add($this->c->registrar, $supplier, 'tax_clearance', ($this->pdf)(), 'TC-1', null, now()->addYear()->toDateString());

    $this->docs->verify($this->c->registrar, $doc);
})->throws(WorkflowViolation::class, 'recorded a document');

it('stores the document file with its hash and freezes it once verified', function () {
    $supplier = ($this->register)();
    $doc = $this->docs->add($this->c->registrar, $supplier, 'tax_clearance', ($this->pdf)(), 'TC-1', null, now()->addYear()->toDateString());
    $this->docs->verify($this->c->verifier, $doc);

    expect($doc->document->versions()->sole()->sha256)->toBe(hash('sha256', '%PDF-1.4 x'))
        ->and(fn () => $doc->fresh()->update(['expires_on' => now()->addYears(5)]))->toThrow(LogicException::class)
        ->and(fn () => $doc->fresh()->delete())->toThrow(LogicException::class);
});

it('requires expiry dates where documents expire and refuses expired ones', function () {
    $supplier = ($this->register)();

    expect(fn () => $this->docs->add($this->c->registrar, $supplier, 'business_permit', ($this->pdf)()))->toThrow(WorkflowViolation::class, 'expiry date');

    $expired = $this->docs->add($this->c->registrar, $supplier, 'business_permit', ($this->pdf)(), 'BP', '2020-01-01', now()->subDay()->toDateString());
    expect(fn () => $this->docs->verify($this->c->verifier, $expired))->toThrow(WorkflowViolation::class, 'expired');
});

it('becomes ineligible on the day a required document expires', function () {
    $supplier = eligibleSupplier($this->c);
    $expiry = $supplier->documents()->where('document_type', 'tax_clearance')->value('expires_on');

    expect($this->eligibility->check($supplier, $expiry)->eligible())->toBeTrue()
        ->and($this->eligibility->check($supplier, $expiry->copy()->addDay())->reasons)->toContain('Tax clearance is missing, unverified or expired');
});

it('caps declared beneficial ownership at 100 percent', function () {
    $supplier = ($this->register)();
    $this->owners->declare($this->c->registrar, $supplier, 'A', '60.5');
    $this->owners->declare($this->c->registrar, $supplier, 'B', '39.5');

    expect(fn () => $this->owners->declare($this->c->registrar, $supplier, 'C', '0.0001'))->toThrow(WorkflowViolation::class, '100%')
        ->and(fn () => $this->owners->declare($this->c->registrar, $supplier, 'D', null))->toThrow(WorkflowViolation::class, 'basis of control');

    expect($this->owners->declare($this->c->registrar, $supplier, 'Trustee', null, null, 'Board control')->exists)->toBeTrue();
});

it('deactivates with a reason and requires re-verification to return', function () {
    $supplier = eligibleSupplier($this->c);
    expect(fn () => $this->status->deactivate($this->c->registrar, $supplier, ''))->toThrow(WorkflowViolation::class, 'reason');

    $this->status->deactivate($this->c->registrar, $supplier, 'Closed business');
    expect($this->eligibility->check($supplier)->reasons)->toContain('the supplier is inactive');

    $this->status->reopen($this->c->registrar, $supplier, 'Reopened');
    expect($supplier->status)->toBe(SupplierStatus::Pending);
    $this->status->activate($this->c->verifier, $supplier);
    expect($this->eligibility->check($supplier)->eligible())->toBeTrue();
});

it('keeps suppliers of other agencies out of reach', function () {
    $foreign = Supplier::factory()->create();

    $this->docs->add($this->c->registrar, $foreign, 'tax_clearance', ($this->pdf)(), null, null, now()->addYear()->toDateString());
})->throws(WorkflowViolation::class, 'another agency');
