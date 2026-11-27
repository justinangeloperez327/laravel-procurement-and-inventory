<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMaintenance extends Model
{
    use Auditable, HasFactory;

    protected $table = 'asset_maintenance';

    protected $fillable = [
        'asset_id',
        'maintenance_type',
        'performed_on',
        'performed_by',
        'cost',
        'description',
        'next_due_on',
    ];

    protected function casts(): array
    {
        return [
            'performed_on' => 'date',
            'cost' => 'decimal:2',
            'next_due_on' => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}
