<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    use Auditable, HasFactory;

    protected $table = 'stock_transfer_items';

    protected $fillable = [
        'stock_transfer_id',
        'item_id',
        'inventory_lot_id',
        'quantity_sent',
        'quantity_received',
    ];

    protected function casts(): array
    {
        return [
            'quantity_sent' => 'decimal:4',
            'quantity_received' => 'decimal:4',
        ];
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
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
