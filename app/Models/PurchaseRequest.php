<?php

namespace App\Models;

use App\Domain\Approvals\Approvable;
use App\Domain\Approvals\HasApprovals;
use App\Domain\Audit\Auditable;
use App\Domain\Shared\FreezesWhenFinal;
use App\Enums\PurchaseRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model implements Approvable
{
    use Auditable, FreezesWhenFinal, HasApprovals, HasFactory;

    protected $table = 'purchase_requests';

    protected $fillable = [
        'agency_id',
        'department_id',
        'fiscal_year_id',
        'reference_no',
        'purpose',
        'requested_by',
        'status',
        'total_amount',
        'app_item_id',
        'fund_source_id',
        'account_code_id',
        'submitted_at',
        'approved_by',
        'approved_at',
        'cancellation_reason',
        'plan_exception_basis',
        'plan_exception_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseRequestStatus::class,
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

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function appItem(): BelongsTo
    {
        return $this->belongsTo(AppItem::class, 'app_item_id');
    }

    public function fundSource(): BelongsTo
    {
        return $this->belongsTo(FundSource::class, 'fund_source_id');
    }

    public function accountCode(): BelongsTo
    {
        return $this->belongsTo(AccountCode::class, 'account_code_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class, 'purchase_request_id');
    }

    public function approvalSubjectType(): string
    {
        return 'purchase_request';
    }

    public function approvalAgencyId(): int
    {
        return $this->agency_id;
    }

    public function approvalDepartment(): ?Department
    {
        return $this->department;
    }

    public function approvalInitiatorId(): ?int
    {
        return $this->requested_by;
    }

    public static function editableStatuses(): array
    {
        return [PurchaseRequestStatus::Draft, PurchaseRequestStatus::Returned];
    }

    protected static function workflowColumns(): array
    {
        return ['status', 'submitted_at', 'approved_by', 'approved_at', 'total_amount', 'cancellation_reason', 'updated_at'];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(BudgetReservation::class, 'purchase_request_id');
    }

    public function activeReservation(): ?BudgetReservation
    {
        return $this->reservations()->where('status', 'active')->first();
    }

    public function hasPlanException(): bool
    {
        return filled($this->plan_exception_basis);
    }
}
