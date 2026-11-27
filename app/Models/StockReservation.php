<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    use Auditable, HasFactory;

    protected $table = 'stock_reservations';

    protected $fillable = [
        'stock_requisition_item_id',
        'warehouse_id',
        'item_id',
        'inventory_lot_id',
        'quantity',
        'status',
        'reserved_by',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'expires_at' => 'datetime',
        ];
    }

    public function stockRequisitionItem(): BelongsTo
    {
        return $this->belongsTo(StockRequisitionItem::class, 'stock_requisition_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function inventoryLot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'inventory_lot_id');
    }

    public function reservedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }
}
