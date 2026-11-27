<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractSecurity extends Model
{
    use Auditable, HasFactory;

    protected $table = 'contract_securities';

    protected $fillable = [
        'contract_id',
        'security_type',
        'form',
        'issuer',
        'reference_no',
        'amount',
        'valid_from',
        'valid_until',
        'status',
        'document_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
