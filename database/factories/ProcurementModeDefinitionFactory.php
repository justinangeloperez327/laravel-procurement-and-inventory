<?php

namespace Database\Factories;

use App\Enums\ProcurementMode;
use App\Models\ProcurementModeDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcurementModeDefinition> */
class ProcurementModeDefinitionFactory extends Factory
{
    protected $model = ProcurementModeDefinition::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->randomElement(array_column(ProcurementMode::cases(), 'value')),
            'name' => fake()->words(2, true),
            'legal_reference' => null,
            'description' => null,
            'is_active' => true,
        ];
    }
}
