<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\ProcurementMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Procurement mode reference row (one per App\Enums\ProcurementMode case). */
class ProcurementModeDefinition extends Model
{
    use Auditable, HasFactory;

    protected $table = 'procurement_modes';

    protected $fillable = [
        'code',
        'name',
        'legal_reference',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'code' => ProcurementMode::class,
            'is_active' => 'boolean',
        ];
    }
}
