<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rfq extends Model
{
    use Auditable, HasFactory;

    protected $table = 'rfqs';

    protected $fillable = [
        'procurement_project_id',
        'procurement_lot_id',
        'reference_no',
        'issued_at',
        'submission_deadline',
        'status',
        'minimum_suppliers',
        'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'submission_deadline' => 'datetime',
            'minimum_suppliers' => 'integer',
        ];
    }

    public function procurementProject(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class, 'procurement_project_id');
    }

    public function procurementLot(): BelongsTo
    {
        return $this->belongsTo(ProcurementLot::class, 'procurement_lot_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'rfq_id');
    }
}
