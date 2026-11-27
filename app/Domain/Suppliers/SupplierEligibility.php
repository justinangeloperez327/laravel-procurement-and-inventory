<?php

namespace App\Domain\Suppliers;

use App\Enums\SupplierStatus;
use App\Models\ComplianceFlag;
use App\Models\Supplier;
use App\Models\SupplierSanction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Whether a supplier may be invited, evaluated or awarded on a given date (L-11, L-14).
 *
 * Computed from the records themselves, not only from the status column, so a sanction or expiry
 * takes effect on its date even before the daily sweep updates the status. Later phases must call
 * assert() before inviting, short-listing, evaluating or awarding.
 */
class SupplierEligibility
{
    public function check(Supplier $supplier, CarbonInterface|string|null $on = null): EligibilityResult
    {
        $date = Carbon::parse($on ?? now())->startOfDay();
        $reasons = [];

        if ($supplier->status === SupplierStatus::Pending) {
            $reasons[] = 'registration has not been verified';
        } elseif ($supplier->status === SupplierStatus::Inactive) {
            $reasons[] = 'the supplier is inactive';
        }

        $sanction = self::activeSanctions($supplier, $date)->orderByDesc('sanction_type')->first();
        if ($sanction) {
            $reasons[] = "{$sanction->sanction_type->label()} under {$sanction->order_reference}"
                .($sanction->effective_to ? ' until '.$sanction->effective_to->toDateString() : '');
        }

        foreach (SupplierDocumentTypes::REQUIRED as $type) {
            $valid = $supplier->documents()
                ->where('document_type', $type)
                ->whereNotNull('verified_at')
                ->where(fn ($q) => $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', $date))
                ->exists();
            if (! $valid) {
                $reasons[] = SupplierDocumentTypes::label($type).' is missing, unverified or expired';
            }
        }

        if (! $supplier->beneficialOwners()->exists()) {
            $reasons[] = 'no beneficial ownership declaration';
        }

        $blocking = ComplianceFlag::query()
            ->where('subject_type', $supplier->getMorphClass())->where('subject_id', $supplier->id)
            ->where('severity', 'blocking')->whereIn('status', ['open', 'escalated'])
            ->pluck('flag_type')->unique();
        foreach ($blocking as $type) {
            $reasons[] = 'open blocking compliance flag ('.str_replace('_', ' ', $type).')';
        }

        return new EligibilityResult($reasons);
    }

    public function assert(Supplier $supplier, CarbonInterface|string|null $on = null): void
    {
        $this->check($supplier, $on)->assert($supplier->name);
    }

    /** Sanctions in force on the date: started, not ended, not lifted. */
    public static function activeSanctions(Supplier $supplier, CarbonInterface $date): Builder
    {
        return SupplierSanction::query()
            ->where('supplier_id', $supplier->id)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>', $date))
            ->where(fn ($q) => $q->whereNull('lifted_at')->orWhere('lifted_at', '>', $date->copy()->endOfDay()));
    }
}
