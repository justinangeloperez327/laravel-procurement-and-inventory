<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Links a mode to the effective-dated regulatory rules that screen it (docs/08). */
class ProcurementModeRule extends Model
{
    use Auditable, HasFactory;

    protected $table = 'procurement_mode_rules';

    protected $fillable = [
        'procurement_mode_id',
        'regulatory_rule_version_id',
        'rule_type',
        'is_blocking',
    ];

    protected function casts(): array
    {
        return [
            'is_blocking' => 'boolean',
        ];
    }

    public function procurementMode(): BelongsTo
    {
        return $this->belongsTo(ProcurementModeDefinition::class, 'procurement_mode_id');
    }

    public function regulatoryRuleVersion(): BelongsTo
    {
        return $this->belongsTo(RegulatoryRuleVersion::class, 'regulatory_rule_version_id');
    }
}
