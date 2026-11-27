<?php

namespace App\Models;

use App\Domain\Shared\Immutable;
use App\Enums\ApprovalDecision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Immutable record of an approval decision, including delegated authority used. */
class ApprovalAction extends Model
{
    use HasFactory, Immutable;

    public const UPDATED_AT = null;

    protected $table = 'approval_actions';

    protected $fillable = [
        'approval_step_id',
        'approvable_type',
        'approvable_id',
        'actor_id',
        'on_behalf_of_id',
        'delegation_id',
        'decision',
        'remarks',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'decision' => ApprovalDecision::class,
            'acted_at' => 'datetime',
        ];
    }

    public function approvalStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalStep::class, 'approval_step_id');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function onBehalfOf(): BelongsTo
    {
        return $this->belongsTo(User::class, 'on_behalf_of_id');
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(Delegation::class, 'delegation_id');
    }
}
