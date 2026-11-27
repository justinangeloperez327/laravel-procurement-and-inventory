<?php

namespace App\Models;

use App\Domain\Shared\Immutable;
use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Immutable stock ledger. Signed quantity; accepted receipts post exactly once per inspection line; corrections are reversals. */
class StockMovement extends Model
{
    use HasFactory, Immutable;

    public const UPDATED_AT = null;

    protected $table = 'stock_movements';

    protected $fillable = [
        'agency_id',
        'warehouse_id',
        'warehouse_location_id',
        'item_id',
        'inventory_lot_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'source_type',
        'source_id',
        'inspection_item_id',
        'reverses_movement_id',
        'idempotency_key',
        'posted_by',
        'posted_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => StockMovementType::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'posted_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function inventoryLot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'inventory_lot_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function inspectionItem(): BelongsTo
    {
        return $this->belongsTo(InspectionItem::class, 'inspection_item_id');
    }

    public function reversesMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'reverses_movement_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
