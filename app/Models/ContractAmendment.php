<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractAmendment extends Model
{
    use Auditable, HasFactory;

    protected $table = 'contract_amendments';

    protected $fillable = [
        'contract_id',
        'amendment_no',
        'amendment_type',
        'description',
        'amount_change',
        'days_extension',
        'approved_by',
        'approved_at',
        'legal_basis',
    ];

    protected function casts(): array
    {
        return [
            'amendment_no' => 'integer',
            'amount_change' => 'decimal:2',
            'days_extension' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
