<?php

namespace Database\Factories;

use App\Enums\AssetClass;
use App\Models\Agency;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Item> */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'item_category_id' => ItemCategory::factory(),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'code' => 'ITM-'.fake()->unique()->numerify('######'),
            'name' => fake()->words(3, true),
            'description' => null,
            'specifications' => null,
            'asset_class' => AssetClass::Consumable,
            'is_serialized' => false,
            'is_lot_tracked' => false,
            'tracks_expiry' => false,
            'is_cse' => false,
            'reorder_level' => null,
            'is_active' => true,
        ];
    }
}
