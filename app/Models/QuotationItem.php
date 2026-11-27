<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use Auditable, HasFactory;

    protected $table = 'quotation_items';

    protected $fillable = [
        'quotation_id',
        'procurement_project_item_id',
        'unit_price',
        'quantity',
        'brand_model',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'quantity' => 'decimal:4',
            'total' => 'decimal:2',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function procurementProjectItem(): BelongsTo
    {
        return $this->belongsTo(ProcurementProjectItem::class, 'procurement_project_item_id');
    }
}
