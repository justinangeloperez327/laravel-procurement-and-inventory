<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    use Auditable, HasFactory;

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
