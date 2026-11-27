<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementProjectItem extends Model
{
    use Auditable, HasFactory;

    protected $table = 'procurement_project_items';

    protected $fillable = [
        'procurement_project_id',
        'procurement_lot_id',
        'purchase_request_item_id',
        'quantity',
        'approved_budget',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'approved_budget' => 'decimal:2',
        ];
    }

    public function procurementProject(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class, 'procurement_project_id');
    }

    public function procurementLot(): BelongsTo
    {
        return $this->belongsTo(ProcurementLot::class, 'procurement_lot_id');
    }

    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }
}
