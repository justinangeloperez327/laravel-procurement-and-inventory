<?php

namespace Database\Factories;

use App\Enums\SanctionType;
use App\Models\Supplier;
use App\Models\SupplierSanction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupplierSanction> */
class SupplierSanctionFactory extends Factory
{
    protected $model = SupplierSanction::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'sanction_type' => SanctionType::Suspension,
            'issuing_authority' => 'GPPB',
            'order_reference' => 'ORDER-'.fake()->numerify('####'),
            'reason' => fake()->sentence(),
            'effective_from' => now(),
            'effective_to' => null,
            'lifted_at' => null,
            'lifted_reason' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
