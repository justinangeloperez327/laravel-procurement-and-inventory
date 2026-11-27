<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Payment reference recorded from the accounting system; GPIMS does not replace government accounting. */
class Payment extends Model
{
    use Auditable, HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'agency_id',
        'reference_no',
        'ors_burs_reference',
        'payee_supplier_id',
        'amount',
        'paid_on',
        'payment_method',
        'recorded_by',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function payeeSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'payee_supplier_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }
}
