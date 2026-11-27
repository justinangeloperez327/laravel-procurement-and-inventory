<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\ProcurementCategory;
use App\Enums\RuleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Effective-dated, reviewed interpretation of a provision. Never hardcode thresholds; activate only approved versions. */
class RegulatoryRuleVersion extends Model
{
    use Auditable, HasFactory;

    protected $table = 'regulatory_rule_versions';

    /** Approved and retired versions are frozen; only the approved -> retired status change is allowed. */
    protected static function booted(): void
    {
        static::updating(function (self $version) {
            $original = $version->getOriginal('status');
            if ($original === RuleStatus::Draft) {
                return;
            }
            $changed = array_diff(array_keys($version->getDirty()), ['status', 'updated_at']);
            $allowed = $original === RuleStatus::Approved && $version->status === RuleStatus::Retired;
            if ($changed !== [] || ! $allowed) {
                throw new LogicException('Approved rule versions are frozen. Create a new version instead.');
            }
        });
        static::deleting(fn (self $version) => $version->getOriginal('status') === RuleStatus::Draft
            ?: throw new LogicException('Only draft rule versions can be deleted.'));
    }

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
        'prepared_by',
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

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
