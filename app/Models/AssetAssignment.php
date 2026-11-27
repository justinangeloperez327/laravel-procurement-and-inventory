<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Custodian assignment via ICS (semi-expendable) or PAR (PPE). */
class AssetAssignment extends Model
{
    use Auditable, HasFactory;

    protected $table = 'asset_assignments';

    protected $fillable = [
        'asset_id',
        'custodian_id',
        'department_id',
        'document_type',
        'document_reference',
        'assigned_at',
        'acknowledged_at',
        'returned_at',
        'return_condition',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
