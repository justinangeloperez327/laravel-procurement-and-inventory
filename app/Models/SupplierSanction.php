<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\SanctionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierSanction extends Model
{
    use Auditable, HasFactory;

    protected $table = 'supplier_sanctions';

    protected $fillable = [
        'supplier_id',
        'sanction_type',
        'issuing_authority',
        'order_reference',
        'reason',
        'effective_from',
        'effective_to',
        'lifted_at',
        'lifted_reason',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'sanction_type' => SanctionType::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
            'lifted_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
