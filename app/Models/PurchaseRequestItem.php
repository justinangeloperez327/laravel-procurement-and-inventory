<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    use Auditable, HasFactory;

    protected $table = 'purchase_request_items';

    protected $fillable = [
        'purchase_request_id',
        'ppmp_item_id',
        'item_id',
        'description',
        'specifications',
        'unit_of_measure_id',
        'quantity',
        'estimated_unit_cost',
        'estimated_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'estimated_unit_cost' => 'decimal:4',
            'estimated_total' => 'decimal:2',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function ppmpItem(): BelongsTo
    {
        return $this->belongsTo(PpmpItem::class, 'ppmp_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }
}
