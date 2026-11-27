<?php

namespace App\Models;

use App\Domain\Approvals\HasApprovals;
use App\Domain\Audit\Auditable;
use App\Domain\Planning\PlanDocument;
use App\Domain\Shared\FreezesWhenFinal;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketScoping extends Model implements PlanDocument
{
    use Auditable, FreezesWhenFinal, HasApprovals, HasFactory;

    protected $table = 'market_scopings';

    protected $fillable = [
        'agency_id',
        'department_id',
        'fiscal_year_id',
        'reference_no',
        'title',
        'description',
        'conducted_by',
        'conducted_on',
        'status',
        'submitted_at',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'conducted_on' => 'date',
            'status' => PlanStatus::class,
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

    public function conductedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketScopingItem::class, 'market_scoping_id');
    }

    public static function editableStatuses(): array
    {
        return [PlanStatus::Draft, PlanStatus::Returned];
    }

    public function approvalSubjectType(): string
    {
        return 'market_scoping';
    }

    public function approvalInitiatorId(): ?int
    {
        return $this->conducted_by;
    }

    public function approvalAgencyId(): int
    {
        return $this->agency_id;
    }

    public function approvalDepartment(): ?Department
    {
        return $this->department;
    }

    public function submissionPermission(): string
    {
        return 'ppmp.create';
    }

    public function assertReadyForSubmission(): void
    {
        if (! $this->items()->exists()) {
            throw new WorkflowViolation('Add at least one scoped item before submitting.');
        }
    }

    public function recalculateTotal(): void {}

    public function afterApproved(User $approver): void {}

    protected static function workflowColumns(): array
    {
        return ['status', 'submitted_at', 'approved_by', 'approved_at', 'updated_at'];
    }
}
