<?php

namespace App\Models;

use App\Domain\Shared\Immutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable signed-off change to an allocation; direction given by adjustment_type. */
class BudgetAdjustment extends Model
{
    use HasFactory, Immutable;

    public const UPDATED_AT = null;

    protected $table = 'budget_adjustments';

    protected $fillable = [
        'budget_allocation_id',
        'adjustment_type',
        'amount',
        'authorization_reference',
        'reason',
        'approved_by',
        'approved_at',
        'related_adjustment_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function budgetAllocation(): BelongsTo
    {
        return $this->belongsTo(BudgetAllocation::class, 'budget_allocation_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function relatedAdjustment(): BelongsTo
    {
        return $this->belongsTo(BudgetAdjustment::class, 'related_adjustment_id');
    }
}
