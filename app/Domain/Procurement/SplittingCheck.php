<?php

namespace App\Domain\Procurement;

use App\Domain\Compliance\ComplianceFlags;
use App\Domain\Shared\Money;
use App\Enums\ProcurementMode;
use App\Models\ProcurementJustification;
use App\Models\ProcurementProject;
use Illuminate\Support\Facades\DB;

/**
 * Splitting review (L-05; docs/16 "Related subthreshold purchases → raise splitting review flag").
 *
 * When a project uses an alternative mode under a ceiling (an abc_max rule), other active projects of
 * the same agency and fiscal year that share an APP item or an item category and also use an
 * alternative mode are added up. If their combined ABC exceeds the ceiling, a warning flag is raised
 * for compliance review. The flag does not block: related purchases can be legitimate.
 */
class SplittingCheck
{
    public function __construct(private ComplianceFlags $flags) {}

    /** @return list<string> Reference numbers of the related projects, if a flag was raised. */
    public function check(ProcurementProject $project, ScreeningResult $screening): array
    {
        $ceiling = $screening->ceilingCents();
        if ($ceiling === null || $screening->mode === ProcurementMode::CompetitiveBidding->value) {
            return [];
        }

        $categories = self::itemCategoryIds($project);
        $candidates = ProcurementProject::query()
            ->where('agency_id', $project->agency_id)
            ->where('fiscal_year_id', $project->fiscal_year_id)
            ->whereKeyNot($project->id)
            ->whereNotIn('status', CreateProcurementProject::INACTIVE)
            ->get()
            ->filter(function (ProcurementProject $other) use ($project, $categories) {
                $sharesApp = $project->app_item_id !== null && $other->app_item_id === $project->app_item_id;

                return ($sharesApp || array_intersect($categories, self::itemCategoryIds($other)) !== []) && self::usesAlternativeMode($other);
            });

        $total = Money::toCents((string) $project->approved_budget)
            + $candidates->sum(fn (ProcurementProject $p) => Money::toCents((string) $p->approved_budget));
        if ($candidates->isEmpty() || $total <= $ceiling) {
            return [];
        }

        $refs = $candidates->pluck('reference_no')->sort()->values()->all();
        $this->flags->raise($project->agency_id, $project, 'possible_splitting', 'warning', sprintf(
            '%s and related projects %s together total %s, above the %s ceiling for %s. Review for possible splitting.',
            $project->reference_no, implode(', ', $refs), Money::format(Money::fromCents($total)),
            Money::format(Money::fromCents($ceiling)), ProcurementMode::from($screening->mode)->label(),
        ));

        return $refs;
    }

    /** @return list<int> */
    public static function itemCategoryIds(ProcurementProject $project): array
    {
        return DB::table('procurement_project_items as ppi')
            ->join('purchase_request_items as pri', 'pri.id', '=', 'ppi.purchase_request_item_id')
            ->join('items', 'items.id', '=', 'pri.item_id')
            ->where('ppi.procurement_project_id', $project->id)
            ->distinct()->pluck('items.item_category_id')->map(fn ($id) => (int) $id)->all();
    }

    private static function usesAlternativeMode(ProcurementProject $project): bool
    {
        $mode = $project->procurementMode?->code
            ?? ProcurementJustification::with('procurementMode')
                ->where('procurement_project_id', $project->id)
                ->whereIn('status', ['proposed', 'endorsed', 'approved'])
                ->latest('id')->first()?->procurementMode?->code;

        return $mode !== null && $mode !== ProcurementMode::CompetitiveBidding;
    }
}
