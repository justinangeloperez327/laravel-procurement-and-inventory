<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostQualification extends Model
{
    use Auditable, HasFactory;

    protected $table = 'post_qualifications';

    protected $fillable = [
        'procurement_project_id',
        'procurement_lot_id',
        'supplier_id',
        'bid_id',
        'quotation_id',
        'started_at',
        'completed_at',
        'result',
        'findings',
        'report_document_id',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'bid_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function reportDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'report_document_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
