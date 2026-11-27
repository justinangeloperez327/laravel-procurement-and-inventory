<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhysicalCountItem extends Model
{
    use Auditable, HasFactory;

    protected $table = 'physical_count_items';

    protected $fillable = [
        'physical_count_id',
        'item_id',
        'inventory_lot_id',
        'system_quantity',
        'counted_quantity',
        'variance_reason',
        'adjustment_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:4',
            'counted_quantity' => 'decimal:4',
        ];
    }

    public function physicalCount(): BelongsTo
    {
        return $this->belongsTo(PhysicalCount::class, 'physical_count_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function inventoryLot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'inventory_lot_id');
    }

    public function adjustmentMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'adjustment_movement_id');
    }
}
