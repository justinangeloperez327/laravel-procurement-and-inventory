<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockIssuanceItem extends Model
{
    use Auditable, HasFactory;

    protected $table = 'stock_issuance_items';

    protected $fillable = [
        'stock_issuance_id',
        'stock_requisition_item_id',
        'item_id',
        'inventory_lot_id',
        'quantity',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function stockIssuance(): BelongsTo
    {
        return $this->belongsTo(StockIssuance::class, 'stock_issuance_id');
    }

    public function stockRequisitionItem(): BelongsTo
    {
        return $this->belongsTo(StockRequisitionItem::class, 'stock_requisition_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function inventoryLot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'inventory_lot_id');
    }
}
