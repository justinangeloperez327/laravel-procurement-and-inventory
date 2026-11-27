<?php

namespace App\Domain\Documents;

use App\Domain\Shared\DomainRuleViolation;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores document content as immutable, hashed versions on the private disk.
 *
 * A new upload always becomes a new version; nothing is overwritten. Content is served only through
 * short-lived signed URLs after a policy check, and every download is audited.
 *
 * Malware scanning is not yet wired in. Add a scanner before accepting uploads from outside the agency.
 */
class DocumentStore
{
    public const DISK = 'local';

    public const MAX_BYTES = 25 * 1024 * 1024;

    /** @var list<string> */
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function addVersion(Document $document, UploadedFile|File $file, User $author, ?string $signedBy = null): DocumentVersion
    {
        $mime = $file->getMimeType();
        $size = $file->getSize();
        if (! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new DomainRuleViolation("Files of type {$mime} are not accepted.");
        }
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new DomainRuleViolation('The file is empty or larger than 25 MB.');
        }

        $hash = hash_file('sha256', $file->getRealPath());
        $originalName = $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename();
        $extension = $file instanceof UploadedFile ? $file->getClientOriginalExtension() : $file->getExtension();

        return DB::transaction(function () use ($document, $file, $author, $hash, $originalName, $extension, $mime, $size) {
            Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $version = (int) DocumentVersion::where('document_id', $document->id)->max('version') + 1;

            $directory = "documents/{$document->agency_id}/{$document->id}";
            $name = "v{$version}-".Str::uuid().($extension ? ".{$extension}" : '');
            $key = Storage::disk(self::DISK)->putFileAs($directory, $file, $name);
            if ($key === false) {
                throw new DomainRuleViolation('The file could not be stored.');
            }

            try {
                $record = DocumentVersion::create([
                    'document_id' => $document->id,
                    'version' => $version,
                    'storage_disk' => self::DISK,
                    'storage_key' => $key,
                    'original_name' => mb_substr($originalName, 0, 255),
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                    'sha256' => $hash,
                    'author_id' => $author->id,
                ]);
            } catch (Throwable $e) {
                Storage::disk(self::DISK)->delete($key);
                throw $e;
            }

            AuditLog::record('document.version_added', $record, null, ['version' => $version, 'sha256' => $hash]);

            return $record;
        });
    }

    /** Recomputes the hash of the stored content and compares it with the recorded SHA-256. */
    public function verify(DocumentVersion $version): bool
    {
        $disk = Storage::disk($version->storage_disk);
        if (! $disk->exists($version->storage_key)) {
            return false;
        }

        return hash_equals($version->sha256, hash('sha256', $disk->get($version->storage_key)));
    }

    public function temporaryUrl(DocumentVersion $version, int $minutes = 5): string
    {
        return URL::temporarySignedRoute('documents.download', now()->addMinutes($minutes), ['version' => $version->id]);
    }
}
