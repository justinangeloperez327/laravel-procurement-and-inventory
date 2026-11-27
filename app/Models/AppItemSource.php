<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Shared\BelongsToEditableParent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lineage from consolidated APP items back to PPMP items. */
class AppItemSource extends Model
{
    use Auditable, BelongsToEditableParent, HasFactory;

    protected $table = 'app_item_sources';

    protected $fillable = [
        'app_item_id',
        'ppmp_item_id',
        'quantity',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    public function appItem(): BelongsTo
    {
        return $this->belongsTo(AppItem::class, 'app_item_id');
    }

    public function ppmpItem(): BelongsTo
    {
        return $this->belongsTo(PpmpItem::class, 'ppmp_item_id');
    }

    protected function editableParent(): ?object
    {
        return $this->appItem?->appVersion;
    }
}
