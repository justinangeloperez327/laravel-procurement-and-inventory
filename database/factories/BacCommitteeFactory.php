<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\BacCommittee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BacCommittee> */
class BacCommitteeFactory extends Factory
{
    protected $model = BacCommittee::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'name' => fake()->words(2, true),
            'committee_type' => 'goods',
            'constituted_on' => now(),
            'office_order_reference' => 'OO-'.fake()->numerify('####'),
            'dissolved_on' => null,
        ];
    }
}
