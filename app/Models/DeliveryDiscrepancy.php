<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryDiscrepancy extends Model
{
    use Auditable, HasFactory;

    protected $table = 'delivery_discrepancies';

    protected $fillable = [
        'delivery_id',
        'delivery_item_id',
        'discrepancy_type',
        'quantity',
        'description',
        'status',
        'resolution',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'resolved_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }

    public function deliveryItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryItem::class, 'delivery_item_id');
    }
}
