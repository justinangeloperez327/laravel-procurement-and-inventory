<?php

namespace App\Domain\Planning;

use App\Enums\PlanStatus;
use App\Models\AppItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which APP items may support spending: only items of the APP version currently approved by the HoPE.
 * Draft, returned, submitted and superseded versions never qualify (docs/16 "Unapproved APP expenditure").
 */
final class AppCoverage
{
    /** @return Builder<AppItem> */
    public static function usableItems(int $agencyId, ?int $fiscalYearId = null): Builder
    {
        return AppItem::query()->whereHas('appVersion', fn (Builder $q) => $q
            ->where('status', PlanStatus::Approved)
            ->whereHas('app', fn (Builder $a) => $a->where('agency_id', $agencyId)
                ->when($fiscalYearId, fn (Builder $f) => $f->where('fiscal_year_id', $fiscalYearId))));
    }

    public static function isUsable(AppItem $item): bool
    {
        return $item->appVersion()->where('status', PlanStatus::Approved)->exists();
    }
}
