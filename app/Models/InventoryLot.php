<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLot extends Model
{
    use Auditable, HasFactory;

    protected $table = 'inventory_lots';

    protected $fillable = [
        'item_id',
        'lot_number',
        'expiry_date',
        'manufactured_on',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'manufactured_on' => 'date',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
