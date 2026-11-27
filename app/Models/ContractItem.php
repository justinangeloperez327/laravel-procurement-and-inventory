<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractItem extends Model
{
    use Auditable, HasFactory;

    protected $table = 'contract_items';

    protected $fillable = [
        'contract_id',
        'procurement_project_item_id',
        'item_id',
        'description',
        'unit_of_measure_id',
        'quantity',
        'unit_price',
        'total',
        'delivery_due_on',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'total' => 'decimal:2',
            'delivery_due_on' => 'date',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function procurementProjectItem(): BelongsTo
    {
        return $this->belongsTo(ProcurementProjectItem::class, 'procurement_project_item_id');
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
