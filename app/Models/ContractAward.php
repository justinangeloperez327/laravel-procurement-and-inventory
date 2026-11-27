<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Award decision; approval is HoPE authority (award.approve). */
class ContractAward extends Model
{
    use Auditable, HasFactory;

    protected $table = 'contract_awards';

    protected $fillable = [
        'procurement_project_id',
        'procurement_lot_id',
        'supplier_id',
        'bac_resolution_id',
        'awarded_bid_id',
        'awarded_quotation_id',
        'awarded_amount',
        'approved_by',
        'approved_at',
        'noa_reference',
        'notice_of_award_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'awarded_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'notice_of_award_at' => 'datetime',
        ];
    }

    public function procurementProject(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class, 'procurement_project_id');
    }

    public function procurementLot(): BelongsTo
    {
        return $this->belongsTo(ProcurementLot::class, 'procurement_lot_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function bacResolution(): BelongsTo
    {
        return $this->belongsTo(BacResolution::class, 'bac_resolution_id');
    }

    public function awardedBid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'awarded_bid_id');
    }

    public function awardedQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'awarded_quotation_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
