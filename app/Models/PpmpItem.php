<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\ProcurementMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PpmpItem extends Model
{
    use Auditable, HasFactory;

    protected $table = 'ppmp_items';

    protected $fillable = [
        'ppmp_id',
        'item_id',
        'market_scoping_item_id',
        'description',
        'unit_of_measure_id',
        'quantity',
        'estimated_unit_cost',
        'estimated_budget',
        'proposed_mode',
        'schedule_start',
        'schedule_end',
        'account_code_id',
        'fund_source_id',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'estimated_unit_cost' => 'decimal:4',
            'estimated_budget' => 'decimal:2',
            'proposed_mode' => ProcurementMode::class,
            'schedule_start' => 'date',
            'schedule_end' => 'date',
        ];
    }

    public function ppmp(): BelongsTo
    {
        return $this->belongsTo(Ppmp::class, 'ppmp_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function marketScopingItem(): BelongsTo
    {
        return $this->belongsTo(MarketScopingItem::class, 'market_scoping_item_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function accountCode(): BelongsTo
    {
        return $this->belongsTo(AccountCode::class, 'account_code_id');
    }

    public function fundSource(): BelongsTo
    {
        return $this->belongsTo(FundSource::class, 'fund_source_id');
    }
}
