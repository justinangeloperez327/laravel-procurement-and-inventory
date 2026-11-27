<?php

namespace App\Domain\Procurement;

use App\Domain\Shared\ReferenceNumbers;
use App\Domain\Shared\TransitionStatus;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\ProcurementCategory;
use App\Enums\ProcurementProjectStatus;
use App\Enums\PurchaseRequestStatus;
use App\Models\FiscalYear;
use App\Models\ProcurementProject;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Builds a procurement project from lines of approved purchase requests. The approved budget for the
 * contract (ABC) is the exact sum of the lines' estimated totals. A line can be in only one active
 * project; lines of a cancelled or failed project can be procured again.
 */
class CreateProcurementProject
{
    /** Project statuses that release their lines for re-procurement. */
    public const INACTIVE = [ProcurementProjectStatus::Cancelled, ProcurementProjectStatus::Failed];

    public function __construct(private ReferenceNumbers $numbers, private TransitionStatus $transition) {}

    /** @param  list<int>  $purchaseRequestItemIds */
    public function __invoke(User $actor, FiscalYear $fiscalYear, string $title, ProcurementCategory $category, array $purchaseRequestItemIds, ?string $description = null): ProcurementProject
    {
        ProcurementGuard::require($actor, 'procurement.manage');
        if ($fiscalYear->agency_id !== $actor->agency_id) {
            throw new WorkflowViolation('The fiscal year belongs to another agency.');
        }
        $ids = array_values(array_unique(array_map('intval', $purchaseRequestItemIds)));
        if ($ids === []) {
            throw new WorkflowViolation('Select at least one approved purchase request line.');
        }

        return DB::transaction(function () use ($actor, $fiscalYear, $title, $category, $ids, $description) {
            // Lock the requests so the same line cannot be put into two projects at once.
            $lines = PurchaseRequestItem::with('purchaseRequest')->whereKey($ids)->get();
            if ($lines->count() !== count($ids)) {
                throw new WorkflowViolation('Some selected lines no longer exist.');
            }
            DB::table('purchase_requests')->whereIn('id', $lines->pluck('purchase_request_id')->unique())->orderBy('id')->lockForUpdate()->get();

            foreach ($lines as $line) {
                $pr = $line->purchaseRequest->fresh();
                if ($pr->agency_id !== $actor->agency_id || $pr->fiscal_year_id !== $fiscalYear->id) {
                    throw new WorkflowViolation("{$pr->reference_no} is not a request of this agency and fiscal year.");
                }
                if (! in_array($pr->status, [PurchaseRequestStatus::Approved, PurchaseRequestStatus::Procurement], true)) {
                    throw new WorkflowViolation("{$pr->reference_no} is not approved ({$pr->status->label()}).");
                }
                if (self::lineInActiveProject($line->id)) {
                    throw new WorkflowViolation("A line of {$pr->reference_no} ({$line->description}) is already in an active procurement project.");
                }
            }

            $prs = $lines->pluck('purchaseRequest')->unique('id');
            $appItemIds = $prs->pluck('app_item_id')->filter()->unique();
            $departments = $prs->pluck('department_id')->unique();

            $project = ProcurementProject::create([
                'agency_id' => $actor->agency_id,
                'fiscal_year_id' => $fiscalYear->id,
                'app_item_id' => $appItemIds->count() === 1 ? $appItemIds->first() : null,
                'reference_no' => $this->numbers->next($actor->agency_id, 'PROJ', $fiscalYear->year),
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'approved_budget' => 0,
                'status' => ProcurementProjectStatus::Proposed,
                'end_user_department_id' => $departments->count() === 1 ? $departments->first() : null,
                'procurement_officer_id' => $actor->id,
                'legal_regime' => 'RA12009',
            ]);

            foreach ($lines as $line) {
                $project->items()->create([
                    'purchase_request_item_id' => $line->id,
                    'quantity' => $line->quantity,
                    'approved_budget' => $line->estimated_total,
                ]);
            }
            self::recomputeBudgets($project);

            foreach ($prs as $pr) {
                $pr = $pr->fresh();
                if ($pr->status === PurchaseRequestStatus::Approved) {
                    ($this->transition)($pr, PurchaseRequestStatus::Procurement, $actor, "Included in {$project->reference_no}");
                }
            }

            return $project->fresh();
        });
    }

    public static function lineInActiveProject(int $purchaseRequestItemId, ?int $exceptProjectId = null): bool
    {
        return DB::table('procurement_project_items as i')
            ->join('procurement_projects as p', 'p.id', '=', 'i.procurement_project_id')
            ->where('i.purchase_request_item_id', $purchaseRequestItemId)
            ->whereNotIn('p.status', array_map(fn ($s) => $s->value, self::INACTIVE))
            ->when($exceptProjectId, fn ($q) => $q->where('p.id', '!=', $exceptProjectId))
            ->exists();
    }

    /** ABC of the project and its lots = exact sums of their lines. */
    public static function recomputeBudgets(ProcurementProject $project): void
    {
        DB::update('UPDATE procurement_lots SET approved_budget = (SELECT COALESCE(SUM(approved_budget), 0) FROM procurement_project_items i WHERE i.procurement_lot_id = procurement_lots.id), updated_at = now() WHERE procurement_project_id = ?', [$project->id]);
        DB::update('UPDATE procurement_projects SET approved_budget = (SELECT COALESCE(SUM(approved_budget), 0) FROM procurement_project_items i WHERE i.procurement_project_id = procurement_projects.id), updated_at = now() WHERE id = ?', [$project->id]);
    }
}
