<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockIssuance extends Model
{
    use Auditable, HasFactory;

    protected $table = 'stock_issuances';

    protected $fillable = [
        'agency_id',
        'stock_requisition_id',
        'warehouse_id',
        'reference_no',
        'issued_to',
        'issued_by',
        'issued_at',
        'acknowledged_at',
        'status',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function stockRequisition(): BelongsTo
    {
        return $this->belongsTo(StockRequisition::class, 'stock_requisition_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function issuedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_to');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockIssuanceItem::class, 'stock_issuance_id');
    }
}
