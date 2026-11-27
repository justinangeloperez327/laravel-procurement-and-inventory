<?php

namespace App\Models;

use App\Domain\Approvals\HasApprovals;
use App\Domain\Audit\Auditable;
use App\Domain\Planning\PlanDocument;
use App\Domain\Shared\FreezesWhenFinal;
use App\Domain\Shared\TransitionStatus;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AppVersion extends Model implements PlanDocument
{
    use Auditable, FreezesWhenFinal, HasApprovals, HasFactory;

    protected $table = 'app_versions';

    protected $fillable = [
        'app_id',
        'version',
        'status',
        'is_indicative',
        'total_amount',
        'prepared_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'revision_reason',
        'supersedes_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => PlanStatus::class,
            'is_indicative' => 'boolean',
            'total_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(AnnualProcurementPlan::class, 'app_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(AppVersion::class, 'supersedes_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AppItem::class, 'app_version_id');
    }

    public static function editableStatuses(): array
    {
        return [PlanStatus::Draft, PlanStatus::Returned];
    }

    public function approvalSubjectType(): string
    {
        return 'app_version';
    }

    public function approvalInitiatorId(): ?int
    {
        return $this->prepared_by;
    }

    public function approvalAgencyId(): int
    {
        return $this->app->agency_id;
    }

    /** The APP is agency-wide. */
    public function approvalDepartment(): ?Department
    {
        return null;
    }

    public function submissionPermission(): string
    {
        return 'app.consolidate';
    }

    public function assertReadyForSubmission(): void
    {
        $items = $this->items()->withCount('sources')->get();
        if ($items->isEmpty()) {
            throw new WorkflowViolation('Consolidate approved PPMPs before submitting the APP.');
        }
        if ($items->contains(fn (AppItem $i) => $i->sources_count === 0)) {
            throw new WorkflowViolation('Every APP item must trace to at least one approved PPMP item.');
        }

        $stale = AppItemSource::query()
            ->whereIn('app_item_id', $this->items()->select('id'))
            ->whereHas('ppmpItem.ppmp', fn ($q) => $q->where(fn ($w) => $w
                ->where('status', '!=', PlanStatus::Approved)
                ->orWhere('agency_id', '!=', $this->app->agency_id)
                ->orWhere('fiscal_year_id', '!=', $this->app->fiscal_year_id)))
            ->exists();
        if ($stale) {
            throw new WorkflowViolation('Some sources are no longer approved PPMP items of this fiscal year. Consolidate again.');
        }

        $mismatch = DB::table('app_items')
            ->where('app_version_id', $this->id)
            ->whereRaw('estimated_budget <> (SELECT COALESCE(SUM(amount), 0) FROM app_item_sources s WHERE s.app_item_id = app_items.id)')
            ->exists();
        if ($mismatch) {
            throw new WorkflowViolation('APP item budgets must equal the sum of their PPMP sources. Consolidate again.');
        }
    }

    public function recalculateTotal(): void
    {
        $total = DB::table('app_items')->where('app_version_id', $this->id)->sum('estimated_budget');
        $this->forceFill(['total_amount' => $total])->save();
    }

    /** Approval supersedes the previous approved version and marks the APP approved. */
    public function afterApproved(User $approver): void
    {
        static::query()
            ->where('app_id', $this->app_id)
            ->where('status', PlanStatus::Approved)
            ->whereKeyNot($this->id)
            ->get()
            ->each(fn (self $older) => app(TransitionStatus::class)($older, PlanStatus::Superseded, $approver, "Superseded by version {$this->version}"));

        $this->app->forceFill(['status' => PlanStatus::Approved])->save();
    }
}
