<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementLot extends Model
{
    use Auditable, HasFactory;

    protected $table = 'procurement_lots';

    protected $fillable = [
        'procurement_project_id',
        'lot_no',
        'title',
        'approved_budget',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'lot_no' => 'integer',
            'approved_budget' => 'decimal:2',
        ];
    }

    public function procurementProject(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class, 'procurement_project_id');
    }
}
