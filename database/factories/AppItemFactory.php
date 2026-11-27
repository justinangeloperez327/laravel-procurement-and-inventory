<?php

namespace Database\Factories;

use App\Enums\ProcurementMode;
use App\Models\AppItem;
use App\Models\AppVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AppItem> */
class AppItemFactory extends Factory
{
    protected $model = AppItem::class;

    public function definition(): array
    {
        return [
            'app_version_id' => AppVersion::factory(),
            'code' => 'PAP-'.fake()->unique()->numerify('#####'),
            'description' => fake()->words(5, true),
            'item_category_id' => null,
            'procurement_mode' => ProcurementMode::SmallValueProcurement,
            'estimated_budget' => fake()->randomFloat(2, 10000, 2000000),
            'fund_source_id' => null,
            'advertisement_on' => null,
            'opening_on' => null,
            'award_on' => null,
            'contract_signing_on' => null,
            'remarks' => null,
        ];
    }
}
