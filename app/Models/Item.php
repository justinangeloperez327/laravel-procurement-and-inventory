<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\AssetClass;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Item extends Model
{
    use Auditable, HasFactory;

    protected $table = 'items';

    protected $fillable = [
        'agency_id',
        'item_category_id',
        'unit_of_measure_id',
        'code',
        'name',
        'description',
        'specifications',
        'asset_class',
        'is_serialized',
        'is_lot_tracked',
        'tracks_expiry',
        'is_cse',
        'reorder_level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'asset_class' => AssetClass::class,
            'is_serialized' => 'boolean',
            'is_lot_tracked' => 'boolean',
            'tracks_expiry' => 'boolean',
            'is_cse' => 'boolean',
            'reorder_level' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function itemCategory(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }
}
