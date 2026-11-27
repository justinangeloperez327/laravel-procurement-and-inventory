<?php

namespace Database\Factories;

use App\Models\MarketScoping;
use App\Models\MarketScopingItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MarketScopingItem> */
class MarketScopingItemFactory extends Factory
{
    protected $model = MarketScopingItem::class;

    public function definition(): array
    {
        return [
            'market_scoping_id' => MarketScoping::factory(),
            'item_id' => null,
            'description' => fake()->words(4, true),
            'unit_of_measure_id' => null,
            'estimated_unit_cost' => fake()->randomFloat(2, 10, 50000),
            'price_source' => 'canvass',
            'sources_count' => null,
            'notes' => null,
        ];
    }
}
