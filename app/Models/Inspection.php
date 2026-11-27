<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\InspectionResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Independent inspection; the inspector must differ from the receiving officer (enforced by domain actions). */
class Inspection extends Model
{
    use Auditable, HasFactory;

    protected $table = 'inspections';

    protected $fillable = [
        'delivery_id',
        'inspected_by',
        'inspected_at',
        'status',
        'result',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'inspected_at' => 'datetime',
            'result' => InspectionResult::class,
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class, 'inspection_id');
    }
}
