<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FiscalYear> */
class FiscalYearFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            // Unique so several factories can create fiscal years for the same agency.
            'year' => fake()->unique()->numberBetween(2000, 2099),
            'starts_on' => fn (array $attributes) => "{$attributes['year']}-01-01",
            'ends_on' => fn (array $attributes) => "{$attributes['year']}-12-31",
            'status' => FiscalYear::STATUS_OPEN,
        ];
    }

    public function forYear(int $year): static
    {
        return $this->state(fn () => ['year' => $year]);
    }
}
