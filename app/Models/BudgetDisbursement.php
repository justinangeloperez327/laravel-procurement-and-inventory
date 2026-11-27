<?php

namespace App\Models;

use App\Domain\Shared\Immutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable. Disbursement reduces unpaid obligations, never the uncommitted allocation a second time (docs/09). */
class BudgetDisbursement extends Model
{
    use HasFactory, Immutable;

    public const UPDATED_AT = null;

    protected $table = 'budget_disbursements';

    protected $fillable = [
        'budget_obligation_id',
        'payment_id',
        'amount',
        'disbursed_at',
        'recorded_by',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'disbursed_at' => 'datetime',
        ];
    }

    public function budgetObligation(): BelongsTo
    {
        return $this->belongsTo(BudgetObligation::class, 'budget_obligation_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
