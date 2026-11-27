<?php

namespace Database\Factories;

use App\Enums\ContractStatus;
use App\Models\Agency;
use App\Models\Contract;
use App\Models\ContractAward;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contract> */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'contract_award_id' => ContractAward::factory(),
            'supplier_id' => fn (array $attributes) => Supplier::factory()->state(['agency_id' => $attributes['agency_id']]),
            'reference_no' => 'CON-'.fake()->unique()->numerify('########'),
            'title' => fake()->sentence(4),
            'contract_amount' => fake()->randomFloat(2, 10000, 1000000),
            'signed_on' => null,
            'effective_on' => null,
            'end_on' => null,
            'notice_to_proceed_at' => null,
            'status' => ContractStatus::Draft,
        ];
    }
}
