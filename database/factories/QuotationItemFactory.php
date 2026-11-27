<?php

namespace Database\Factories;

use App\Models\ProcurementProjectItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuotationItem> */
class QuotationItemFactory extends Factory
{
    protected $model = QuotationItem::class;

    public function definition(): array
    {
        return [
            'quotation_id' => Quotation::factory(),
            'procurement_project_item_id' => ProcurementProjectItem::factory(),
            'unit_price' => fake()->randomFloat(2, 10, 5000),
            'quantity' => fake()->numberBetween(1, 50),
            'brand_model' => null,
            'remarks' => null,
        ];
    }
}
