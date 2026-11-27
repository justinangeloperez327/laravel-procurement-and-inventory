<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Deadlines are extended by new rows linked to the original, never by overwriting. */
class ProcurementDeadline extends Model
{
    use Auditable, HasFactory;

    protected $table = 'procurement_deadlines';

    protected $fillable = [
        'procurement_project_id',
        'deadline_type',
        'due_at',
        'legal_basis',
        'regulatory_rule_version_id',
        'extended_from_id',
        'extension_reason',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
        ];
    }

    public function procurementProject(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class, 'procurement_project_id');
    }

    public function regulatoryRuleVersion(): BelongsTo
    {
        return $this->belongsTo(RegulatoryRuleVersion::class, 'regulatory_rule_version_id');
    }

    public function extendedFrom(): BelongsTo
    {
        return $this->belongsTo(ProcurementDeadline::class, 'extended_from_id');
    }
}
