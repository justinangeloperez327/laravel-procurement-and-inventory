<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Eligibility document with expiry tracking. */
class SupplierDocument extends Model
{
    use Auditable, HasFactory;

    /** A verified document is final; record a renewal as a new document. */
    protected static function booted(): void
    {
        $guard = function (self $doc) {
            if ($doc->getOriginal('verified_at') !== null) {
                throw new LogicException('Verified supplier documents cannot be changed or removed.');
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    protected $table = 'supplier_documents';

    protected $fillable = [
        'supplier_id',
        'document_id',
        'document_type',
        'reference_no',
        'issued_on',
        'expires_on',
        'recorded_by',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
