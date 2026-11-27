<?php

namespace Database\Factories;

use App\Models\BacCommittee;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Meeting> */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    public function definition(): array
    {
        return [
            'bac_committee_id' => BacCommittee::factory(),
            'procurement_project_id' => null,
            'meeting_type' => 'regular',
            'scheduled_at' => now()->addDay(),
            'held_at' => null,
            'venue' => null,
            'quorum_met' => null,
            'minutes_document_id' => null,
        ];
    }
}
