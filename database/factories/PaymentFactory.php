<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'reference_no' => 'DV-'.fake()->unique()->numerify('########'),
            'ors_burs_reference' => null,
            'payee_supplier_id' => fn (array $attributes) => Supplier::factory()->state(['agency_id' => $attributes['agency_id']]),
            'amount' => fake()->randomFloat(2, 1000, 500000),
            'paid_on' => now(),
            'payment_method' => 'ada',
            'recorded_by' => fn (array $attributes) => User::factory()->state(['agency_id' => $attributes['agency_id']]),
            'idempotency_key' => fake()->uuid(),
        ];
    }
}
