<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBeneficialOwner extends Model
{
    use Auditable, HasFactory;

    protected $table = 'supplier_beneficial_owners';

    protected $fillable = [
        'supplier_id',
        'name',
        'nationality',
        'ownership_percentage',
        'control_basis',
        'declared_on',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'ownership_percentage' => 'decimal:4',
            'declared_on' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
