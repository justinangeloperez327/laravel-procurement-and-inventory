<?php

namespace App\Domain\Suppliers;

use App\Domain\Compliance\ComplianceFlags;
use App\Enums\SupplierStatus;
use App\Models\Supplier;
use Illuminate\Support\Carbon;

/**
 * Daily sweep: aligns supplier status with sanctions that start or end today, and raises notices for
 * expired and soon-to-expire required documents. Eligibility checks do not depend on the sweep; it
 * keeps statuses and the compliance queue current.
 */
class SupplierComplianceSweep
{
    public function __construct(private ManageSupplierStatus $status, private ComplianceFlags $flags) {}

    /** @return array{status_changes: int, expired: int, expiring: int} */
    public function run(?int $agencyId = null): array
    {
        $today = Carbon::today();
        $notice = $today->copy()->addDays(SupplierDocumentTypes::EXPIRY_NOTICE_DAYS);
        $counts = ['status_changes' => 0, 'expired' => 0, 'expiring' => 0];

        Supplier::query()
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
            ->where('status', '!=', SupplierStatus::Inactive)
            ->with('documents')
            ->chunkById(200, function ($suppliers) use ($today, $notice, &$counts) {
                foreach ($suppliers as $supplier) {
                    $before = $supplier->status;
                    if ($this->status->syncWithSanctions($supplier)->status !== $before) {
                        $counts['status_changes']++;
                    }

                    foreach (SupplierDocumentTypes::REQUIRED as $type) {
                        $docs = $supplier->documents->where('document_type', $type)->whereNotNull('verified_at');
                        $latestExpiry = $docs->contains(fn ($d) => $d->expires_on === null)
                            ? null
                            : $docs->max(fn ($d) => $d->expires_on);
                        if ($docs->isEmpty() || $latestExpiry === null) {
                            continue;
                        }

                        $label = SupplierDocumentTypes::label($type);
                        if ($latestExpiry->lt($today)) {
                            $this->flags->raise($supplier->agency_id, $supplier, "expired_document:{$type}", 'warning',
                                "{$label} expired on {$latestExpiry->toDateString()}.");
                            $counts['expired']++;
                        } elseif ($latestExpiry->lte($notice)) {
                            $this->flags->raise($supplier->agency_id, $supplier, "expiring_document:{$type}", 'info',
                                "{$label} expires on {$latestExpiry->toDateString()}.");
                            $counts['expiring']++;
                        }
                    }
                }
            });

        return $counts;
    }
}
