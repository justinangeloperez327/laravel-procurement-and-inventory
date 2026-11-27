<?php

namespace App\Http\Controllers;

use App\Domain\Documents\DocumentStore;
use App\Models\AuditLog;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves a document version through a signed, short-lived URL after a policy check. */
class DocumentDownloadController
{
    public function __invoke(Request $request, DocumentVersion $version, DocumentStore $store): StreamedResponse
    {
        $document = $version->document;

        if (Gate::denies('download', $document)) {
            AuditLog::record('document.download_denied', $version, null, null, ['document_id' => $document->id]);
            abort(403);
        }

        abort_unless($store->verify($version), 409, 'Stored content does not match its recorded hash.');

        AuditLog::record('document.downloaded', $version, null, null, ['document_id' => $document->id]);

        return Storage::disk($version->storage_disk)->download($version->storage_key, $version->original_name, [
            'Content-Type' => $version->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
