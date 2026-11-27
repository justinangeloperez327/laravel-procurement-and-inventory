<?php

namespace Database\Factories;

use App\Models\ProcurementEvent;
use App\Models\ProcurementProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcurementEvent> */
class ProcurementEventFactory extends Factory
{
    protected $model = ProcurementEvent::class;

    public function definition(): array
    {
        return [
            'procurement_project_id' => ProcurementProject::factory(),
            'event_type' => 'pre_bid_conference',
            'scheduled_at' => now()->addWeek(),
            'held_at' => null,
            'venue' => null,
            'remarks' => null,
            'recorded_by' => null,
        ];
    }
}
