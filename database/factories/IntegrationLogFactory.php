<?php

namespace Database\Factories;

use App\Models\IntegrationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IntegrationLog> */
class IntegrationLogFactory extends Factory
{
    protected $model = IntegrationLog::class;

    public function definition(): array
    {
        return [
            'system' => 'philgeps',
            'direction' => 'outbound',
            'operation' => 'post_opportunity',
            'subject_type' => null,
            'subject_id' => null,
            'idempotency_key' => null,
            'request_payload' => null,
            'response_payload' => null,
            'status' => 'succeeded',
            'http_status' => null,
            'error' => null,
            'occurred_at' => now(),
        ];
    }
}
