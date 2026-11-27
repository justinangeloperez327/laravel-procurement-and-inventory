<?php

namespace Database\Factories;

use App\Models\BacCommittee;
use App\Models\BacResolution;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BacResolution> */
class BacResolutionFactory extends Factory
{
    protected $model = BacResolution::class;

    public function definition(): array
    {
        return [
            'bac_committee_id' => BacCommittee::factory(),
            'procurement_project_id' => null,
            'meeting_id' => null,
            'resolution_no' => 'RES-'.fake()->unique()->numerify('####'),
            'title' => fake()->sentence(4),
            'resolution_type' => 'recommend_award',
            'body' => null,
            'adopted_on' => now(),
            'document_id' => null,
        ];
    }
}
