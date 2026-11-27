<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Protest; while unresolved and blocks_award is set, award must stop (docs/16). */
class Protest extends Model
{
    use Auditable, HasFactory;

    protected $table = 'protests';

    protected $fillable = [
        'procurement_project_id',
        'supplier_id',
        'filed_at',
        'grounds',
        'protest_fee',
        'status',
        'blocks_award',
        'recorded_by',
        'decided_by',
        'decided_at',
        'decision',
    ];

    protected function casts(): array
    {
        return [
            'filed_at' => 'datetime',
            'protest_fee' => 'decimal:2',
            'blocks_award' => 'boolean',
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

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
