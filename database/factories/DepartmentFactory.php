<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Department> */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'code' => strtoupper(fake()->unique()->bothify('DEP-###')),
            'name' => fake()->words(2, true).' Division',
            'is_active' => true,
        ];
    }
}
