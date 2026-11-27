<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\DocumentClassification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A controlled record; content lives in immutable document versions. */
class Document extends Model
{
    use Auditable, HasFactory;

    protected $table = 'documents';

    protected $fillable = [
        'agency_id',
        'documentable_type',
        'documentable_id',
        'document_type',
        'title',
        'classification',
        'retention_policy',
        'unsealed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'classification' => DocumentClassification::class,
            'unsealed_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'document_id');
    }
}
