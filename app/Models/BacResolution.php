<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class BacResolution extends Model
{
    use Auditable, HasFactory;

    /** Adopted resolutions are part of the record; issue a new resolution to amend one. */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('BAC resolutions cannot be edited.'));
        static::deleting(fn () => throw new LogicException('BAC resolutions cannot be deleted.'));
    }

    protected $table = 'bac_resolutions';

    protected $fillable = [
        'bac_committee_id',
        'procurement_project_id',
        'meeting_id',
        'resolution_no',
        'title',
        'resolution_type',
        'body',
        'adopted_on',
        'document_id',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'adopted_on' => 'date',
        ];
    }

    public function bacCommittee(): BelongsTo
    {
        return $this->belongsTo(BacCommittee::class, 'bac_committee_id');
    }

    public function procurementProject(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class, 'procurement_project_id');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
