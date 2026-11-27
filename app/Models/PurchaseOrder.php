<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use Auditable, HasFactory;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'agency_id',
        'contract_id',
        'contract_award_id',
        'supplier_id',
        'reference_no',
        'issued_on',
        'delivery_due_on',
        'delivery_place',
        'total_amount',
        'status',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'delivery_due_on' => 'date',
            'total_amount' => 'decimal:2',
            'status' => PurchaseOrderStatus::class,
            'acknowledged_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function contractAward(): BelongsTo
    {
        return $this->belongsTo(ContractAward::class, 'contract_award_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }
}
