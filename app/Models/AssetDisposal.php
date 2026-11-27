<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDisposal extends Model
{
    use Auditable, HasFactory;

    protected $table = 'asset_disposals';

    protected $fillable = [
        'asset_id',
        'disposal_method',
        'iirup_reference',
        'reason',
        'approved_by',
        'approved_at',
        'disposed_on',
        'proceeds',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'disposed_on' => 'date',
            'proceeds' => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
