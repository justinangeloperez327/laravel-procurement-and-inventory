<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractSecurity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractSecurity> */
class ContractSecurityFactory extends Factory
{
    protected $model = ContractSecurity::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'security_type' => 'performance',
            'form' => 'surety_bond',
            'issuer' => null,
            'reference_no' => null,
            'amount' => fake()->randomFloat(2, 1000, 100000),
            'valid_from' => now(),
            'valid_until' => now()->addYear(),
            'status' => 'submitted',
            'document_id' => null,
        ];
    }
}
