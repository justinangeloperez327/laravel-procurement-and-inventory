<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    use Auditable, HasFactory;

    protected $table = 'deliveries';

    protected $fillable = [
        'agency_id',
        'purchase_order_id',
        'supplier_id',
        'warehouse_id',
        'reference_no',
        'delivered_at',
        'received_by',
        'received_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'received_at' => 'datetime',
            'status' => DeliveryStatus::class,
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class, 'delivery_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class, 'delivery_id');
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(DeliveryDiscrepancy::class, 'delivery_id');
    }
}
