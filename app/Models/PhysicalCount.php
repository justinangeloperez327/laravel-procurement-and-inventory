<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhysicalCount extends Model
{
    use Auditable, HasFactory;

    protected $table = 'physical_counts';

    protected $fillable = [
        'agency_id',
        'warehouse_id',
        'reference_no',
        'count_date',
        'status',
        'counted_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'count_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PhysicalCountItem::class, 'physical_count_id');
    }
}
