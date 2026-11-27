<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementPublication extends Model
{
    use Auditable, HasFactory;

    protected $table = 'procurement_publications';

    protected $fillable = [
        'procurement_project_id',
        'channel',
        'reference_no',
        'posted_at',
        'removed_at',
        'posted_by',
        'document_id',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function procurementProject(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class, 'procurement_project_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
