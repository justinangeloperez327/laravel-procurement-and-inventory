<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallOffOrder extends Model
{
    use Auditable, HasFactory;

    protected $table = 'call_off_orders';

    protected $fillable = [
        'framework_agreement_id',
        'purchase_order_id',
        'reference_no',
        'ordered_on',
        'amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'ordered_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function frameworkAgreement(): BelongsTo
    {
        return $this->belongsTo(FrameworkAgreement::class, 'framework_agreement_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
