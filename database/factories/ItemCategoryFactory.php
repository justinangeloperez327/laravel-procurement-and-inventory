<?php

namespace Database\Factories;

use App\Models\ItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ItemCategory> */
class ItemCategoryFactory extends Factory
{
    protected $model = ItemCategory::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'code' => 'CAT-'.fake()->unique()->numerify('####'),
            'name' => fake()->words(2, true),
            'default_asset_class' => null,
            'account_code_id' => null,
            'is_active' => true,
        ];
    }
}
