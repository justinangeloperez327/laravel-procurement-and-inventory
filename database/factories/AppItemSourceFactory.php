<?php

namespace Database\Factories;

use App\Models\AppItem;
use App\Models\AppItemSource;
use App\Models\PpmpItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AppItemSource> */
class AppItemSourceFactory extends Factory
{
    protected $model = AppItemSource::class;

    public function definition(): array
    {
        return [
            'app_item_id' => AppItem::factory(),
            'ppmp_item_id' => PpmpItem::factory(),
            'quantity' => null,
            'amount' => fake()->randomFloat(2, 1000, 50000),
        ];
    }
}
