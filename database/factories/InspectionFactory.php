<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Inspection> */
class InspectionFactory extends Factory
{
    protected $model = Inspection::class;

    public function definition(): array
    {
        return [
            'delivery_id' => Delivery::factory(),
            'inspected_by' => User::factory(),
            'inspected_at' => null,
            'status' => 'pending',
            'result' => null,
            'remarks' => null,
        ];
    }
}
