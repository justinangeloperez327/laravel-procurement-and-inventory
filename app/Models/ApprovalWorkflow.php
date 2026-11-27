<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Configurable approval route for a kind of record. */
class ApprovalWorkflow extends Model
{
    use Auditable, HasFactory;

    protected $table = 'approval_workflows';

    protected $fillable = [
        'agency_id',
        'code',
        'name',
        'subject_type',
        'is_active',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'effective_from' => 'date',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class, 'approval_workflow_id');
    }
}
