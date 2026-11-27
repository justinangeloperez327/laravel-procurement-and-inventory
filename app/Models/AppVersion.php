<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppVersion extends Model
{
    use Auditable, HasFactory;

    protected $table = 'app_versions';

    protected $fillable = [
        'app_id',
        'version',
        'status',
        'is_indicative',
        'total_amount',
        'prepared_by',
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
}
