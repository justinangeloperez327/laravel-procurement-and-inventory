<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Published evaluation criteria; locked_at marks the point after which they may not be altered (docs/16). */
class EvaluationCriterion extends Model
{
    use Auditable, HasFactory;

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
}
