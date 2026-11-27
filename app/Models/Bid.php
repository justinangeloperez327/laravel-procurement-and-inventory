<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Sealed bid. bid_amount stays null until authorized opening; submitted_at is the immutable receipt timestamp. */
class Bid extends Model
{
    use Auditable, HasFactory;

    /**
     * The receipt (time, reference, bidder) is immutable. The amount is recorded once, at authorized
     * opening, and never changed.
     */
    protected static function booted(): void
    {
        static::updating(function (self $bid) {
            $frozen = array_intersect(array_keys($bid->getDirty()), ['bidding_id', 'supplier_id', 'procurement_lot_id', 'submitted_at', 'receipt_reference', 'is_late', 'received_by']);
            if ($frozen !== []) {
                throw new LogicException('Bid receipt details cannot be changed.');
            }
            if ($bid->isDirty('bid_amount') && ($bid->getOriginal('bid_amount') !== null || $bid->opened_at === null)) {
                throw new LogicException('The bid amount is recorded once, at opening.');
            }
        });
        static::deleting(fn () => throw new LogicException('Bids cannot be deleted.'));
    }

    protected $table = 'bids';

    protected $fillable = [
        'bidding_id',
        'supplier_id',
        'procurement_lot_id',
        'submitted_at',
        'receipt_reference',
        'bid_amount',
        'is_late',
        'opened_at',
        'opened_by',
        'status',
        'received_by',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'withdrawn_at' => 'datetime',
            'submitted_at' => 'datetime',
            'bid_amount' => 'decimal:2',
            'is_late' => 'boolean',
            'opened_at' => 'datetime',
        ];
    }

    public function bidding(): BelongsTo
    {
        return $this->belongsTo(Bidding::class, 'bidding_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function procurementLot(): BelongsTo
    {
        return $this->belongsTo(ProcurementLot::class, 'procurement_lot_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BidDocument::class, 'bid_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(BidEvaluation::class, 'bid_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
