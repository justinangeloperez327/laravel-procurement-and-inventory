<?php

namespace App\Models;

use App\Domain\Shared\Immutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Immutable log of calls to authorized external systems. */
class IntegrationLog extends Model
{
    use HasFactory, Immutable;

    public const UPDATED_AT = null;

    protected $table = 'integration_logs';

    protected $fillable = [
        'system',
        'direction',
        'operation',
        'subject_type',
        'subject_id',
        'idempotency_key',
        'request_payload',
        'response_payload',
        'status',
        'http_status',
        'error',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'http_status' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
