<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Shared\FreezesWhenFinal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Approved allocation. Available balance = amount + adjustments - active reservations - obligations (docs/09). */
class BudgetAllocation extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CLOSED = 'closed';

    use Auditable, FreezesWhenFinal, HasFactory;

    protected $table = 'budget_allocations';

    protected $fillable = [
        'agency_id',
        'fiscal_year_id',
        'department_id',
        'fund_source_id',
        'account_code_id',
        'reference_no',
        'amount',
        'authorization_reference',
        'recorded_by',
        'approved_by',
        'approved_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function fundSource(): BelongsTo
    {
        return $this->belongsTo(FundSource::class, 'fund_source_id');
    }

    public function accountCode(): BelongsTo
    {
        return $this->belongsTo(AccountCode::class, 'account_code_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(BudgetAdjustment::class, 'budget_allocation_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(BudgetReservation::class, 'budget_allocation_id');
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(BudgetObligation::class, 'budget_allocation_id');
    }

    /** Approved allocations change only through budget adjustments. */
    public static function editableStatuses(): array
    {
        return [self::STATUS_DRAFT];
    }

    protected static function workflowColumns(): array
    {
        return ['status', 'approved_by', 'approved_at', 'updated_at'];
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
