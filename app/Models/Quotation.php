<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use Auditable, HasFactory;

    protected $table = 'quotations';

    protected $fillable = [
        'rfq_id',
        'supplier_id',
        'received_at',
        'total_amount',
        'validity_until',
        'status',
        'is_late',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'validity_until' => 'date',
            'is_late' => 'boolean',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class, 'rfq_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class, 'quotation_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
