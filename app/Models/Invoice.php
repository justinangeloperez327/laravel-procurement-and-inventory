<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use Auditable, HasFactory;

    protected $table = 'invoices';

    protected $fillable = [
        'agency_id',
        'supplier_id',
        'purchase_order_id',
        'contract_id',
        'invoice_number',
        'invoice_date',
        'received_at',
        'total_amount',
        'status',
        'matched_by',
        'matched_at',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'received_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'matched_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'invoice_id');
    }
}
