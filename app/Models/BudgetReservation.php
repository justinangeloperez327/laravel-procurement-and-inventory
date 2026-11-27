<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class BudgetReservation extends Model
{
    use Auditable, HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RELEASED = 'released';

    public const STATUS_CONVERTED = 'converted';

    protected $table = 'budget_reservations';

    /** The reserved amount and allotment are fixed; only an active reservation may be released or converted. */
    protected static function booted(): void
    {
        static::updating(function (self $reservation) {
            $changed = array_diff(array_keys($reservation->getDirty()), ['status', 'released_at', 'released_by', 'release_reason', 'updated_at']);
            if ($changed !== [] || $reservation->getOriginal('status') !== self::STATUS_ACTIVE) {
                throw new LogicException('Budget reservations cannot be edited; release it and reserve again.');
            }
        });
        static::deleting(fn () => throw new LogicException('Budget reservations cannot be deleted.'));
    }

    protected $fillable = [
        'budget_allocation_id',
        'purchase_request_id',
        'amount',
        'status',
        'reserved_by',
        'reserved_at',
        'released_at',
        'released_by',
        'release_reason',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reserved_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function budgetAllocation(): BelongsTo
    {
        return $this->belongsTo(BudgetAllocation::class, 'budget_allocation_id');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function reservedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
