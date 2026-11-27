<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bidding extends Model
{
    use Auditable, HasFactory;

    protected $table = 'biddings';

    protected $fillable = [
        'procurement_project_id',
        'procurement_lot_id',
        'reference_no',
        'bid_submission_deadline',
        'bid_opening_at',
        'status',
        'bidding_documents_fee',
    ];

    protected function casts(): array
    {
        return [
            'bid_submission_deadline' => 'datetime',
            'bid_opening_at' => 'datetime',
            'bidding_documents_fee' => 'decimal:2',
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

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class, 'bidding_id');
    }
}
