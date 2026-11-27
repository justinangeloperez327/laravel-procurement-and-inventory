<?php

namespace App\Domain\Planning;

use App\Domain\Shared\WorkflowViolation;
use App\Enums\PlanStatus;
use App\Enums\ProcurementMode;
use App\Models\AppItem;
use App\Models\AppItemSource;
use App\Models\AppVersion;
use App\Models\AuditLog;
use App\Models\ItemCategory;
use App\Models\PpmpItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Synchronizes a draft APP version with the agency's approved PPMPs for the fiscal year.
 *
 * - Sources pointing at PPMP items that are no longer approved (e.g. superseded by a revision) are removed.
 * - Approved PPMP items not yet in the APP are added, grouped into APP items by procurement mode,
 *   item category and fund source.
 * - Each APP item's budget is recomputed in the database as the exact sum of its sources; items left
 *   without sources are removed.
 *
 * Running it again is safe: it only applies the differences (L-01 PPMP-to-APP lineage).
 */
class ConsolidatePpmps
{
    /** @return array{added: int, removed: int, items: int} */
    public function __invoke(User $actor, AppVersion $version): array
    {
        PlanGuards::requirePermission($actor, 'app.consolidate');
        if ($actor->agency_id !== $version->app->agency_id) {
            throw new WorkflowViolation('The APP belongs to another agency.');
        }

        return DB::transaction(function () use ($version) {
            $locked = AppVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isEditable()) {
                throw new WorkflowViolation('Only a draft or returned APP version can be consolidated.');
            }
            $app = $locked->app;

            $stale = AppItemSource::query()
                ->whereIn('app_item_id', $locked->items()->select('id'))
                ->whereDoesntHave('ppmpItem.ppmp', fn ($q) => $q->where('status', PlanStatus::Approved)
                    ->where('agency_id', $app->agency_id)->where('fiscal_year_id', $app->fiscal_year_id))
                ->get();
            $stale->each->delete();

            $sourced = AppItemSource::whereIn('app_item_id', $locked->items()->select('id'))->pluck('ppmp_item_id');
            $new = PpmpItem::query()
                ->with('item')
                ->whereHas('ppmp', fn ($q) => $q->where('status', PlanStatus::Approved)
                    ->where('agency_id', $app->agency_id)->where('fiscal_year_id', $app->fiscal_year_id))
                ->whereNotIn('id', $sourced)
                ->orderBy('id')
                ->get();

            foreach ($new->groupBy(fn (PpmpItem $i) => $this->groupKey($i)) as $group) {
                /** @var PpmpItem $first */
                $first = $group->first();
                $appItem = $this->appItemFor($locked, $first);
                foreach ($group as $ppmpItem) {
                    $appItem->sources()->create([
                        'ppmp_item_id' => $ppmpItem->id,
                        'quantity' => $ppmpItem->quantity,
                        'amount' => $ppmpItem->estimated_budget,
                    ]);
                }
            }

            DB::update('UPDATE app_items SET estimated_budget = (SELECT COALESCE(SUM(amount), 0) FROM app_item_sources s WHERE s.app_item_id = app_items.id), updated_at = now() WHERE app_version_id = ?', [$locked->id]);
            $locked->items()->whereDoesntHave('sources')->get()->each->delete();
            $locked->recalculateTotal();

            $result = ['added' => $new->count(), 'removed' => $stale->count(), 'items' => $locked->items()->count()];
            AuditLog::record('app.consolidated', $locked, null, $result);

            return $result;
        });
    }

    private function groupKey(PpmpItem $item): string
    {
        return implode('|', [$item->proposed_mode?->value, $item->item?->item_category_id, $item->fund_source_id]);
    }

    private function appItemFor(AppVersion $version, PpmpItem $sample): AppItem
    {
        $categoryId = $sample->item?->item_category_id;
        $existing = $version->items()
            ->where('procurement_mode', $sample->proposed_mode)
            ->where(fn ($q) => $categoryId ? $q->where('item_category_id', $categoryId) : $q->whereNull('item_category_id'))
            ->where(fn ($q) => $sample->fund_source_id ? $q->where('fund_source_id', $sample->fund_source_id) : $q->whereNull('fund_source_id'))
            ->first();
        if ($existing) {
            return $existing;
        }

        $mode = $sample->proposed_mode ?? ProcurementMode::SmallValueProcurement;
        $category = $categoryId ? ItemCategory::find($categoryId)?->name : 'Uncategorized items';
        $next = (int) $version->items()->count() + 1;

        return $version->items()->create([
            'code' => sprintf('%s-%03d', $version->app->reference_no, $next),
            'description' => "{$category} — {$mode->label()}",
            'item_category_id' => $categoryId,
            'procurement_mode' => $mode,
            'estimated_budget' => 0,
            'fund_source_id' => $sample->fund_source_id,
        ]);
    }
}
