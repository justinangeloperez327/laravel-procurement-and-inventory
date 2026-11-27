<?php

namespace App\Domain\Suppliers;

use App\Domain\Shared\TransitionStatus;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\SupplierStatus;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Carbon;

/** Activation, deactivation and status synchronization with sanctions. */
class ManageSupplierStatus
{
    public function __construct(private TransitionStatus $transition, private SupplierEligibility $eligibility) {}

    /** A verifier activates a pending supplier once documents and ownership are verified. */
    public function activate(User $verifier, Supplier $supplier): Supplier
    {
        SupplierGuard::require($verifier, 'supplier.verify', $supplier);
        if ($supplier->status !== SupplierStatus::Pending) {
            throw new WorkflowViolation('Only pending suppliers can be activated.');
        }
        if ($supplier->beneficialOwners()->whereNull('verified_at')->exists()) {
            throw new WorkflowViolation('Verify the beneficial ownership declarations first.');
        }

        // Check everything except the status itself, which is what we are about to change.
        $reasons = array_values(array_filter(
            $this->eligibility->check($supplier)->reasons,
            fn (string $r) => $r !== 'registration has not been verified',
        ));
        if ($reasons !== []) {
            throw new WorkflowViolation('Cannot activate: '.implode('; ', $reasons));
        }

        return ($this->transition)($supplier, SupplierStatus::Active, $verifier, 'Registration verified');
    }

    public function deactivate(User $actor, Supplier $supplier, string $reason): Supplier
    {
        SupplierGuard::require($actor, 'supplier.manage', $supplier);

        return ($this->transition)($supplier, SupplierStatus::Inactive, $actor, $reason);
    }

    /** An inactive supplier goes back to pending and must be verified again. */
    public function reopen(User $actor, Supplier $supplier, string $reason): Supplier
    {
        SupplierGuard::require($actor, 'supplier.manage', $supplier);

        return ($this->transition)($supplier, SupplierStatus::Pending, $actor, $reason);
    }

    /**
     * Aligns the status with sanctions in force today: suspended or blacklisted while a sanction
     * applies, back to active when it ends or is lifted. Pending and inactive suppliers are left alone.
     */
    public function syncWithSanctions(Supplier $supplier, ?User $actor = null): Supplier
    {
        if (in_array($supplier->status, [SupplierStatus::Pending, SupplierStatus::Inactive], true)) {
            return $supplier;
        }

        $active = SupplierEligibility::activeSanctions($supplier, Carbon::today())->get();
        $target = match (true) {
            $active->contains(fn ($s) => $s->sanction_type->value === 'blacklisting') => SupplierStatus::Blacklisted,
            $active->isNotEmpty() => SupplierStatus::Suspended,
            default => SupplierStatus::Active,
        };

        if ($target === $supplier->status) {
            return $supplier;
        }
        $reason = $active->isNotEmpty()
            ? 'Sanction in force: '.$active->pluck('order_reference')->implode(', ')
            : 'No sanction in force';

        return ($this->transition)($supplier, $target, $actor, $reason);
    }
}
