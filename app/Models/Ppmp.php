<?php

namespace App\Models;

use App\Domain\Approvals\HasApprovals;
use App\Domain\Audit\Auditable;
use App\Domain\Planning\PlanDocument;
use App\Domain\Shared\FreezesWhenFinal;
use App\Domain\Shared\TransitionStatus;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** Project Procurement Management Plan. Revisions are new versions linked by parent_ppmp_id. */
class Ppmp extends Model implements PlanDocument
{
    use Auditable, FreezesWhenFinal, HasApprovals, HasFactory;

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

    public static function editableStatuses(): array
    {
        return [PlanStatus::Draft, PlanStatus::Returned];
    }

    public function approvalSubjectType(): string
    {
        return 'ppmp';
    }

    public function approvalInitiatorId(): ?int
    {
        return $this->prepared_by;
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
            throw new WorkflowViolation('Add at least one item before submitting the PPMP.');
        }
        if ($this->items()->whereNull('proposed_mode')->exists()) {
            throw new WorkflowViolation('Every PPMP item needs a proposed procurement mode.');
        }
    }

    public function recalculateTotal(): void
    {
        $total = DB::table('ppmp_items')->where('ppmp_id', $this->id)->sum('estimated_budget');
        $this->forceFill(['total_amount' => $total])->save();
    }

    /** Approving a revision supersedes the earlier approved version of the same PPMP. */
    public function afterApproved(User $approver): void
    {
        static::query()
            ->where('agency_id', $this->agency_id)
            ->where('reference_no', $this->reference_no)
            ->where('status', PlanStatus::Approved)
            ->whereKeyNot($this->id)
            ->get()
            ->each(fn (self $older) => app(TransitionStatus::class)($older, PlanStatus::Superseded, $approver, "Superseded by version {$this->version}"));
    }

    /** All versions sharing this PPMP's reference number, newest first. */
    public function versions(): Builder
    {
        return static::query()->where('agency_id', $this->agency_id)->where('reference_no', $this->reference_no)->orderByDesc('version');
    }
}
