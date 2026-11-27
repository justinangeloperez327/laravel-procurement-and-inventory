<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\StatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StatusHistory> */
class StatusHistoryFactory extends Factory
{
    protected $model = StatusHistory::class;

    public function definition(): array
    {
        return [
            'subject_type' => (new Agency)->getMorphClass(),
            'subject_id' => Agency::factory(),
            'from_status' => null,
            'to_status' => 'submitted',
            'actor_id' => null,
            'reason' => null,
            'changed_at' => now(),
        ];
    }
}
