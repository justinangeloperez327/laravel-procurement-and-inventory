<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Sealed bid. bid_amount stays null until authorized opening; submitted_at is the immutable receipt timestamp. */
class Bid extends Model
{
    use Auditable, HasFactory;

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
    ];

    protected function casts(): array
    {
        return [
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
}
