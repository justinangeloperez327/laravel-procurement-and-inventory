<?php

namespace App\Domain\Suppliers;

use App\Domain\Documents\DocumentStore;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\DocumentClassification;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\User;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Eligibility documents: recorded with their file, then verified by a different person. */
class SupplierDocuments
{
    public function __construct(private DocumentStore $store) {}

    public function add(
        User $actor,
        Supplier $supplier,
        string $type,
        UploadedFile|File $file,
        ?string $referenceNo = null,
        ?string $issuedOn = null,
        ?string $expiresOn = null,
    ): SupplierDocument {
        SupplierGuard::require($actor, 'supplier.manage', $supplier);
        if (! array_key_exists($type, SupplierDocumentTypes::TYPES)) {
            throw new WorkflowViolation("Unknown document type {$type}.");
        }
        if (SupplierDocumentTypes::TYPES[$type]['expires'] && blank($expiresOn)) {
            throw new WorkflowViolation(SupplierDocumentTypes::label($type).' needs an expiry date.');
        }
        if ($issuedOn && $expiresOn && $expiresOn < $issuedOn) {
            throw new WorkflowViolation('The expiry date is before the issue date.');
        }

        return DB::transaction(function () use ($actor, $supplier, $type, $file, $referenceNo, $issuedOn, $expiresOn) {
            $document = Document::create([
                'agency_id' => $supplier->agency_id,
                'documentable_type' => $supplier->getMorphClass(),
                'documentable_id' => $supplier->id,
                'document_type' => "supplier.{$type}",
                'title' => SupplierDocumentTypes::label($type)." — {$supplier->name}",
                'classification' => DocumentClassification::Internal,
                'created_by' => $actor->id,
            ]);
            $this->store->addVersion($document, $file, $actor);

            return SupplierDocument::create([
                'supplier_id' => $supplier->id,
                'document_id' => $document->id,
                'document_type' => $type,
                'reference_no' => $referenceNo,
                'issued_on' => $issuedOn,
                'expires_on' => $expiresOn,
                'recorded_by' => $actor->id,
            ]);
        });
    }

    public function verify(User $verifier, SupplierDocument $document): SupplierDocument
    {
        SupplierGuard::require($verifier, 'supplier.verify', $document->supplier);
        if ($document->recorded_by === $verifier->id) {
            throw new WorkflowViolation('The person who recorded a document may not verify it.');
        }

        return DB::transaction(function () use ($verifier, $document) {
            $locked = SupplierDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($locked->verified_at !== null) {
                throw new WorkflowViolation('The document is already verified.');
            }
            if ($locked->expires_on !== null && $locked->expires_on->isPast() && ! $locked->expires_on->isToday()) {
                throw new WorkflowViolation('An expired document cannot be verified. Record the renewed document instead.');
            }

            $locked->forceFill(['verified_by' => $verifier->id, 'verified_at' => now()])->save();
            AuditLog::record('supplier_document.verified', $locked, null, ['type' => $locked->document_type]);

            return $locked;
        });
    }
}
