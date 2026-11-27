<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Project Procurement Management Plan. Revisions are new versions linked by parent_ppmp_id. */
class Ppmp extends Model
{
    use Auditable, HasFactory;

    protected $table = 'ppmps';

    protected $fillable = [
        'agency_id',
        'department_id',
        'fiscal_year_id',
        'reference_no',
        'version',
        'parent_ppmp_id',
        'status',
        'total_amount',
        'prepared_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => PlanStatus::class,
            'total_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function parentPpmp(): BelongsTo
    {
        return $this->belongsTo(Ppmp::class, 'parent_ppmp_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PpmpItem::class, 'ppmp_id');
    }
}
