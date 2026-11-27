<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementEvent extends Model
{
    use Auditable, HasFactory;

    protected $table = 'procurement_events';

    protected $fillable = [
        'procurement_project_id',
        'event_type',
        'scheduled_at',
        'held_at',
        'venue',
        'remarks',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'held_at' => 'datetime',
        ];
    }

    public function procurementProject(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class, 'procurement_project_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
