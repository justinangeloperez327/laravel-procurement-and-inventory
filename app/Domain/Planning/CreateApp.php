<?php

namespace App\Domain\Planning;

use App\Domain\Shared\ReferenceNumbers;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\PlanStatus;
use App\Models\AnnualProcurementPlan;
use App\Models\AppVersion;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Opens the agency's APP for a fiscal year with an empty first version. */
class CreateApp
{
    public function __construct(private ReferenceNumbers $numbers) {}

    public function __invoke(User $actor, FiscalYear $fiscalYear, bool $indicative = false): AppVersion
    {
        PlanGuards::requirePermission($actor, 'app.consolidate');
        PlanGuards::requireOpenFiscalYear($fiscalYear, $actor->agency_id);

        return DB::transaction(function () use ($actor, $fiscalYear, $indicative) {
            if (AnnualProcurementPlan::where('agency_id', $fiscalYear->agency_id)->where('fiscal_year_id', $fiscalYear->id)->lockForUpdate()->exists()) {
                throw new WorkflowViolation("The APP for {$fiscalYear->year} already exists. Revise it instead.");
            }

            $app = AnnualProcurementPlan::create([
                'agency_id' => $fiscalYear->agency_id,
                'fiscal_year_id' => $fiscalYear->id,
                'reference_no' => $this->numbers->next($fiscalYear->agency_id, 'APP', $fiscalYear->year),
                'title' => "Annual Procurement Plan {$fiscalYear->year}",
                'status' => PlanStatus::Draft,
            ]);

            return $app->versions()->create([
                'version' => 1,
                'status' => PlanStatus::Draft,
                'is_indicative' => $indicative,
                'total_amount' => 0,
                'prepared_by' => $actor->id,
            ]);
        });
    }
}
