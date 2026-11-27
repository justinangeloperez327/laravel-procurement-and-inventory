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
        $year = (int) now()->format('Y');

        return [
            'agency_id' => Agency::factory(),
            'year' => $year,
            'starts_on' => "{$year}-01-01",
            'ends_on' => "{$year}-12-31",
            'status' => FiscalYear::STATUS_OPEN,
        ];
    }
}
