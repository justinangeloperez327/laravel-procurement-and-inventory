<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryItem extends Model
{
    use Auditable, HasFactory;

    protected $table = 'delivery_items';

    protected $fillable = [
        'delivery_id',
        'purchase_order_item_id',
        'quantity_delivered',
        'lot_number',
        'expiry_date',
        'serial_numbers',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delivered' => 'decimal:4',
            'expiry_date' => 'date',
            'serial_numbers' => 'array',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }
}
