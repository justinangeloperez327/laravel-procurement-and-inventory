<?php

namespace App\Models;

use App\Domain\Shared\Immutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Immutable state-transition history for workflow records. */
class StatusHistory extends Model
{
    use HasFactory, Immutable;

    public const UPDATED_AT = null;

    protected $table = 'status_histories';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'from_status',
        'to_status',
        'actor_id',
        'reason',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
