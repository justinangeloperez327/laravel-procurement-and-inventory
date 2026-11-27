<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Shared\FreezesWhenFinal;
use App\Enums\ProcurementCategory;
use App\Enums\ProcurementProjectStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A procurement undertaking. legal_regime records the applicable law (docs/02 L-15). */
class ProcurementProject extends Model
{
    use Auditable, FreezesWhenFinal, HasFactory;

    protected $table = 'procurement_projects';

    protected $fillable = [
        'agency_id',
        'fiscal_year_id',
        'app_item_id',
        'reference_no',
        'title',
        'description',
        'category',
        'procurement_mode_id',
        'approved_budget',
        'status',
        'end_user_department_id',
        'procurement_officer_id',
        'legal_regime',
    ];

    protected function casts(): array
    {
        return [
            'category' => ProcurementCategory::class,
            'approved_budget' => 'decimal:2',
            'status' => ProcurementProjectStatus::class,
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

    public function appItem(): BelongsTo
    {
        return $this->belongsTo(AppItem::class, 'app_item_id');
    }

    public function procurementMode(): BelongsTo
    {
        return $this->belongsTo(ProcurementModeDefinition::class, 'procurement_mode_id');
    }

    public function endUserDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'end_user_department_id');
    }

    public function procurementOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'procurement_officer_id');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(ProcurementLot::class, 'procurement_project_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementProjectItem::class, 'procurement_project_id');
    }

    public function justifications(): HasMany
    {
        return $this->hasMany(ProcurementJustification::class, 'procurement_project_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProcurementEvent::class, 'procurement_project_id');
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(ProcurementDeadline::class, 'procurement_project_id');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(ProcurementPublication::class, 'procurement_project_id');
    }

    /** Title, scope and lines are set while proposed; afterwards only workflow fields change. */
    public static function editableStatuses(): array
    {
        return [ProcurementProjectStatus::Proposed];
    }

    protected static function workflowColumns(): array
    {
        return ['status', 'procurement_mode_id', 'approved_budget', 'updated_at'];
    }
}
