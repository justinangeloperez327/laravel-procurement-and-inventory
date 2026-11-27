<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** An authoritative legal or regulatory issuance (law, IRR, GPPB resolution, COA/DBM circular). */
class RegulatorySource extends Model
{
    use Auditable, HasFactory;

    protected $table = 'regulatory_sources';

    protected $fillable = [
        'code',
        'title',
        'issuer',
        'reference',
        'url',
        'issued_on',
        'effective_from',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'effective_from' => 'date',
        ];
    }
}
