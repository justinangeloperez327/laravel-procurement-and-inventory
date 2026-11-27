<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierBeneficialOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupplierBeneficialOwner> */
class SupplierBeneficialOwnerFactory extends Factory
{
    protected $model = SupplierBeneficialOwner::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'name' => fake()->name(),
            'nationality' => null,
            'ownership_percentage' => 25,
            'control_basis' => null,
            'declared_on' => now(),
            'verified_at' => null,
        ];
    }
}
