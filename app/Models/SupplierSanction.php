<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\SanctionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SupplierSanction extends Model
{
    use Auditable, HasFactory;

    /** Orders are recorded once; only the lifting fields may be set afterwards. Never deleted. */
    protected static function booted(): void
    {
        static::updating(function (self $sanction) {
            $changed = array_diff(array_keys($sanction->getDirty()), ['lifted_at', 'lifted_by', 'lifted_reason', 'updated_at']);
            if ($changed !== [] || $sanction->getOriginal('lifted_at') !== null) {
                throw new LogicException('Sanctions cannot be edited; record a new order or lift this one.');
            }
        });
        static::deleting(fn () => throw new LogicException('Sanctions cannot be deleted.'));
    }

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
        'lifted_by',
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

    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }
}
