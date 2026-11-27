<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SupplierBeneficialOwner extends Model
{
    use Auditable, HasFactory;

    /** A verified declaration is final; record a new declaration for changes in ownership. */
    protected static function booted(): void
    {
        $guard = function (self $owner) {
            if ($owner->getOriginal('verified_at') !== null) {
                throw new LogicException('Verified ownership declarations cannot be changed or removed.');
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    protected $table = 'supplier_beneficial_owners';

    protected $fillable = [
        'supplier_id',
        'name',
        'nationality',
        'ownership_percentage',
        'control_basis',
        'declared_on',
        'verified_by',
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

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
