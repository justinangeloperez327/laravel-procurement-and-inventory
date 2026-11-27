<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Annual Procurement Plan; content lives in versions. */
class AnnualProcurementPlan extends Model
{
    use Auditable, HasFactory;

    protected $table = 'apps';

    protected $fillable = [
        'agency_id',
        'fiscal_year_id',
        'reference_no',
        'title',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
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

    public function versions(): HasMany
    {
        return $this->hasMany(AppVersion::class, 'app_id');
    }
}
