<?php

namespace App\Models;

use App\Domain\Shared\Immutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable stored version of a document with SHA-256 hash. New versions supersede; nothing is overwritten. */
class DocumentVersion extends Model
{
    use HasFactory, Immutable;

    public const UPDATED_AT = null;

    protected $table = 'document_versions';

    protected $fillable = [
        'document_id',
        'version',
        'storage_disk',
        'storage_key',
        'original_name',
        'mime_type',
        'size_bytes',
        'sha256',
        'author_id',
        'signer_id',
        'signed_at',
        'transaction_reference',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'size_bytes' => 'integer',
            'signed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_id');
    }
}
