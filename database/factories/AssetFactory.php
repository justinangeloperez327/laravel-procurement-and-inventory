<?php

namespace Database\Factories;

use App\Enums\AssetClass;
use App\Models\Agency;
use App\Models\Asset;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'property_number' => 'PN-'.fake()->unique()->numerify('########'),
            'item_id' => fn (array $attributes) => Item::factory()->state(['agency_id' => $attributes['agency_id']]),
            'asset_class' => AssetClass::Ppe,
            'inspection_item_id' => null,
            'purchase_order_item_id' => null,
            'acquisition_date' => now(),
            'acquisition_cost' => fake()->randomFloat(2, 5000, 500000),
            'serial_number' => null,
            'brand_model' => null,
            'description' => null,
            'condition' => 'serviceable',
            'status' => 'in_stock',
            'warehouse_id' => null,
            'department_id' => null,
            'custodian_id' => null,
            'warranty_until' => null,
            'estimated_useful_life_years' => null,
        ];
    }
}
