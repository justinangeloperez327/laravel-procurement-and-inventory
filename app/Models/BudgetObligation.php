<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetObligation extends Model
{
    use Auditable, HasFactory;

    protected $table = 'budget_obligations';

    protected $fillable = [
        'budget_allocation_id',
        'budget_reservation_id',
        'contract_id',
        'purchase_order_id',
        'ors_burs_reference',
        'amount',
        'obligated_by',
        'obligated_at',
        'status',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'obligated_at' => 'datetime',
        ];
    }

    public function budgetAllocation(): BelongsTo
    {
        return $this->belongsTo(BudgetAllocation::class, 'budget_allocation_id');
    }

    public function budgetReservation(): BelongsTo
    {
        return $this->belongsTo(BudgetReservation::class, 'budget_reservation_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function obligatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'obligated_by');
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(BudgetDisbursement::class, 'budget_obligation_id');
    }
}
