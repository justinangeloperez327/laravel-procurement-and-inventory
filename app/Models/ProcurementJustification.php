<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\ApprovalDecision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mode screening justification and reviewer decision. */
class ProcurementJustification extends Model
{
    use Auditable, HasFactory;

    protected $table = 'procurement_justifications';

    protected $fillable = [
        'procurement_project_id',
        'procurement_mode_id',
        'regulatory_rule_version_id',
        'justification',
        'evidence',
        'prepared_by',
        'reviewed_by',
        'reviewed_at',
        'decision',
        'decision_remarks',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'reviewed_at' => 'datetime',
            'decision' => ApprovalDecision::class,
        ];
    }

    public function procurementProject(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class, 'procurement_project_id');
    }

    public function procurementMode(): BelongsTo
    {
        return $this->belongsTo(ProcurementModeDefinition::class, 'procurement_mode_id');
    }

    public function regulatoryRuleVersion(): BelongsTo
    {
        return $this->belongsTo(RegulatoryRuleVersion::class, 'regulatory_rule_version_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
