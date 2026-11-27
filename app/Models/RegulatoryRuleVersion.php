<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\ProcurementCategory;
use App\Enums\RuleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Effective-dated, reviewed interpretation of a provision. Never hardcode thresholds; activate only approved versions. */
class RegulatoryRuleVersion extends Model
{
    use Auditable, HasFactory;

    protected $table = 'regulatory_rule_versions';

    protected $fillable = [
        'regulatory_source_id',
        'rule_code',
        'version',
        'provision',
        'legal_reference',
        'entity_type',
        'category',
        'conditions',
        'exceptions',
        'approved_interpretation',
        'effective_from',
        'effective_to',
        'reviewed_by',
        'reviewed_at',
        'test_reference',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'category' => ProcurementCategory::class,
            'conditions' => 'array',
            'exceptions' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'reviewed_at' => 'datetime',
            'status' => RuleStatus::class,
        ];
    }

    public function regulatorySource(): BelongsTo
    {
        return $this->belongsTo(RegulatorySource::class, 'regulatory_source_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
