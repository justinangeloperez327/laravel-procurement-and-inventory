<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    use Auditable, HasFactory;

    protected $table = 'disputes';

    protected $fillable = [
        'agency_id',
        'contract_id',
        'supplier_id',
        'dispute_type',
        'description',
        'filed_at',
        'status',
        'resolution',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'filed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
