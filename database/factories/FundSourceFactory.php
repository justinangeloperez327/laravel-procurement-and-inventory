<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\FundSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FundSource> */
class FundSourceFactory extends Factory
{
    protected $model = FundSource::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'code' => 'FS-'.fake()->unique()->numerify('####'),
            'name' => fake()->words(2, true),
            'description' => null,
            'is_active' => true,
        ];
    }
}
