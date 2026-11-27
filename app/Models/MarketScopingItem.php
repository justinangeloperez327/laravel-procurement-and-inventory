<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketScopingItem extends Model
{
    use Auditable, HasFactory;

    protected $table = 'market_scoping_items';

    protected $fillable = [
        'market_scoping_id',
        'item_id',
        'description',
        'unit_of_measure_id',
        'estimated_unit_cost',
        'price_source',
        'sources_count',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'estimated_unit_cost' => 'decimal:4',
            'sources_count' => 'integer',
        ];
    }

    public function marketScoping(): BelongsTo
    {
        return $this->belongsTo(MarketScoping::class, 'market_scoping_id');
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
