<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidDocument extends Model
{
    use Auditable, HasFactory;

    protected $table = 'bid_documents';

    protected $fillable = [
        'bid_id',
        'document_id',
        'envelope',
        'is_sealed',
    ];

    protected function casts(): array
    {
        return [
            'is_sealed' => 'boolean',
        ];
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'bid_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
