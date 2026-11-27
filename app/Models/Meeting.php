<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Meeting extends Model
{
    use Auditable, HasFactory;

    /** A held meeting (attendance and quorum recorded) is frozen, except for attaching minutes. */
    protected static function booted(): void
    {
        static::updating(function (self $meeting) {
            if ($meeting->getOriginal('held_at') !== null && array_diff(array_keys($meeting->getDirty()), ['minutes_document_id', 'updated_at']) !== []) {
                throw new LogicException('Held meetings cannot be changed.');
            }
        });
        static::deleting(fn (self $meeting) => $meeting->getOriginal('held_at') === null ?: throw new LogicException('Held meetings cannot be deleted.'));
    }

    protected $table = 'meetings';

    protected $fillable = [
        'bac_committee_id',
        'procurement_project_id',
        'meeting_type',
        'scheduled_at',
        'held_at',
        'venue',
        'quorum_met',
        'minutes_document_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'held_at' => 'datetime',
            'quorum_met' => 'boolean',
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

    public function minutesDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'minutes_document_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class, 'meeting_id');
    }
}
