<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\AssetClass;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use Auditable, HasFactory;

    protected $table = 'assets';

    protected $fillable = [
        'agency_id',
        'property_number',
        'item_id',
        'asset_class',
        'inspection_item_id',
        'purchase_order_item_id',
        'acquisition_date',
        'acquisition_cost',
        'serial_number',
        'brand_model',
        'description',
        'condition',
        'status',
        'warehouse_id',
        'department_id',
        'custodian_id',
        'warranty_until',
        'estimated_useful_life_years',
    ];

    protected function casts(): array
    {
        return [
            'asset_class' => AssetClass::class,
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'warranty_until' => 'date',
            'estimated_useful_life_years' => 'integer',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function inspectionItem(): BelongsTo
    {
        return $this->belongsTo(InspectionItem::class, 'inspection_item_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class, 'asset_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(AssetTransfer::class, 'asset_id');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class, 'asset_id');
    }

    public function disposals(): HasMany
    {
        return $this->hasMany(AssetDisposal::class, 'asset_id');
    }
}
