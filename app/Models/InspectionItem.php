<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionItem extends Model
{
    use Auditable, HasFactory;

    protected $table = 'inspection_items';

    protected $fillable = [
        'inspection_id',
        'delivery_item_id',
        'quantity_accepted',
        'quantity_rejected',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity_accepted' => 'decimal:4',
            'quantity_rejected' => 'decimal:4',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class, 'inspection_id');
    }

    public function deliveryItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryItem::class, 'delivery_item_id');
    }
}
