<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconsiderationRequest extends Model
{
    use Auditable, HasFactory;

    protected $table = 'reconsideration_requests';

    protected $fillable = [
        'procurement_project_id',
        'supplier_id',
        'bac_resolution_id',
        'filed_at',
        'grounds',
        'status',
        'recorded_by',
        'decided_by',
        'decided_at',
        'decision',
    ];

    protected function casts(): array
    {
        return [
            'filed_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function procurementProject(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class, 'procurement_project_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function bacResolution(): BelongsTo
    {
        return $this->belongsTo(BacResolution::class, 'bac_resolution_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
