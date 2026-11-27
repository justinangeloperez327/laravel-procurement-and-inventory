<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractAmendment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractAmendment> */
class ContractAmendmentFactory extends Factory
{
    protected $model = ContractAmendment::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'amendment_no' => fake()->unique()->numberBetween(1, 999),
            'amendment_type' => 'extension',
            'description' => fake()->sentence(),
            'amount_change' => 0,
            'days_extension' => null,
            'approved_by' => null,
            'approved_at' => null,
            'legal_basis' => null,
        ];
    }
}
