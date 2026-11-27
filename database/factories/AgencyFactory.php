<?php

namespace Database\Factories;

use App\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Agency> */
class AgencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('AG-####')),
            'name' => fake()->company().' Office',
            'is_active' => true,
        ];
    }
}
