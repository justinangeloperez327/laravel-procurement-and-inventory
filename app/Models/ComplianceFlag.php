<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Review flag (e.g. possible splitting, expired document, sanctioned supplier). raised_by null means system-raised. */
class ComplianceFlag extends Model
{
    use Auditable, HasFactory;

    protected $table = 'compliance_flags';

    protected $fillable = [
        'agency_id',
        'subject_type',
        'subject_id',
        'flag_type',
        'severity',
        'description',
        'raised_by',
        'raised_at',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'raised_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
