<?php

namespace App\Domain\Budget;

use App\Domain\Planning\PlanGuards;
use App\Domain\Shared\Money;
use App\Domain\Shared\ReferenceNumbers;
use App\Domain\Shared\WorkflowViolation;
use App\Models\AccountCode;
use App\Models\BudgetAllocation;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Records a draft allotment against its authorization document (e.g. GAA, SARO, sub-allotment). */
class CreateBudgetAllocation
{
    public function __construct(private ReferenceNumbers $numbers) {}

    public function __invoke(
        User $actor,
        FiscalYear $fiscalYear,
        FundSource $fundSource,
        AccountCode $accountCode,
        string $amount,
        string $authorizationReference,
        ?Department $department = null,
    ): BudgetAllocation {
        PlanGuards::requirePermission($actor, 'budget.allocate');
        PlanGuards::requireOpenFiscalYear($fiscalYear, $actor->agency_id);
        if ($fundSource->agency_id !== $actor->agency_id || ($department && $department->agency_id !== $actor->agency_id)) {
            throw new WorkflowViolation('Fund source and department must belong to your agency.');
        }
        if (Money::toCents($amount) <= 0) {
            throw new WorkflowViolation('The allotment amount must be positive.');
        }
        if (blank($authorizationReference)) {
            throw new WorkflowViolation('An authorization reference is required.');
        }

        return DB::transaction(fn () => BudgetAllocation::create([
            'agency_id' => $actor->agency_id,
            'fiscal_year_id' => $fiscalYear->id,
            'department_id' => $department?->id,
            'fund_source_id' => $fundSource->id,
            'account_code_id' => $accountCode->id,
            'reference_no' => $this->numbers->next($actor->agency_id, 'ALLOT', $fiscalYear->year),
            'amount' => Money::fromCents(Money::toCents($amount)),
            'authorization_reference' => $authorizationReference,
            'recorded_by' => $actor->id,
            'status' => BudgetAllocation::STATUS_DRAFT,
        ]));
    }
}
