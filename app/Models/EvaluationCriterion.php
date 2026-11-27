<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Published evaluation criteria; locked_at marks the point after which they may not be altered (docs/16). */
class EvaluationCriterion extends Model
{
    use Auditable, HasFactory;

    /** Published criteria are locked; they can never be changed or removed (docs/16). */
    protected static function booted(): void
    {
        $guard = function (self $criterion) {
            if ($criterion->getOriginal('locked_at') !== null) {
                throw new LogicException('Published evaluation criteria are locked and cannot be changed.');
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    protected $table = 'evaluation_criteria';

    protected $fillable = [
        'procurement_project_id',
        'procurement_lot_id',
        'sequence',
        'criterion',
        'description',
        'criterion_type',
        'weight',
        'is_published',
        'published_at',
        'locked_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'weight' => 'decimal:4',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function procurementProject(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class, 'procurement_project_id');
    }

    public function procurementLot(): BelongsTo
    {
        return $this->belongsTo(ProcurementLot::class, 'procurement_lot_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
