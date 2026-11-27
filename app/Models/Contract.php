<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use Auditable, HasFactory;

    protected $table = 'contracts';

    protected $fillable = [
        'agency_id',
        'contract_award_id',
        'supplier_id',
        'reference_no',
        'title',
        'contract_amount',
        'signed_on',
        'effective_on',
        'end_on',
        'notice_to_proceed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'contract_amount' => 'decimal:2',
            'signed_on' => 'date',
            'effective_on' => 'date',
            'end_on' => 'date',
            'notice_to_proceed_at' => 'datetime',
            'status' => ContractStatus::class,
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function contractAward(): BelongsTo
    {
        return $this->belongsTo(ContractAward::class, 'contract_award_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContractItem::class, 'contract_id');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ContractAmendment::class, 'contract_id');
    }

    public function securities(): HasMany
    {
        return $this->hasMany(ContractSecurity::class, 'contract_id');
    }
}
