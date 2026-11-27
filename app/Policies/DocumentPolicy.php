<?php

namespace App\Policies;

use App\Enums\DocumentClassification;
use App\Models\Document;
use App\Models\User;

/**
 * Default document access by classification. Later phases tighten this per document type
 * (for example, bid envelopes are readable only by the BAC after authorized opening).
 */
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        if (! $user->is_active || $user->agency_id !== $document->agency_id) {
            return false;
        }

        $privileged = $user->id === $document->created_by || $user->hasPermission('audit.read');

        return match ($document->classification) {
            DocumentClassification::Public, DocumentClassification::Internal => true,
            DocumentClassification::Confidential => $privileged,
            // Sealed bid content is unreadable by anyone until authorized opening sets unsealed_at.
            DocumentClassification::SealedBid => $document->unsealed_at !== null
                && ($privileged || $user->hasPermission('bid.evaluate') || $user->hasPermission('bid.open')),
        };
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function update(User $user, Document $document): bool
    {
        return false;
    }

    public function delete(User $user, Document $document): bool
    {
        return false;
    }
}
