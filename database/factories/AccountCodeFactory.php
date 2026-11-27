<?php

namespace Database\Factories;

use App\Models\AccountCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccountCode> */
class AccountCodeFactory extends Factory
{
    protected $model = AccountCode::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('5########'),
            'name' => fake()->words(2, true),
            'classification' => 'MOOE',
            'parent_id' => null,
            'is_active' => true,
        ];
    }
}
