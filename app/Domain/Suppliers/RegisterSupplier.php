<?php

namespace App\Domain\Suppliers;

use App\Domain\Shared\WorkflowViolation;
use App\Enums\SupplierStatus;
use App\Models\Supplier;
use App\Models\User;

/** Creates a pending supplier record. It becomes eligible only after verification and activation. */
class RegisterSupplier
{
    /** @param  array{name: string, trade_name?: ?string, tin?: ?string, philgeps_registration_no?: ?string, business_type: string, address?: ?string, email?: ?string, phone?: ?string}  $data */
    public function __invoke(User $actor, array $data): Supplier
    {
        SupplierGuard::require($actor, 'supplier.manage');

        $tin = isset($data['tin']) ? preg_replace('/\D/', '', $data['tin']) : null;
        if ($tin !== null && $tin !== '' && Supplier::where('agency_id', $actor->agency_id)->where('tin', $tin)->exists()) {
            throw new WorkflowViolation('A supplier with this TIN is already registered.');
        }

        return Supplier::create([
            ...$data,
            'tin' => $tin ?: null,
            'agency_id' => $actor->agency_id,
            'status' => SupplierStatus::Pending,
            'registered_at' => now()->toDateString(),
        ]);
    }
}
