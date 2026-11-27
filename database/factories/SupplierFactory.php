<?php

namespace Database\Factories;

use App\Enums\SupplierStatus;
use App\Models\Agency;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'name' => fake()->company(),
            'trade_name' => null,
            'philgeps_registration_no' => null,
            'tin' => fake()->unique()->numerify('###-###-###-000'),
            'business_type' => 'corporation',
            'address' => null,
            'email' => fake()->companyEmail(),
            'phone' => null,
            'status' => SupplierStatus::Active,
            'registered_at' => null,
        ];
    }
}
