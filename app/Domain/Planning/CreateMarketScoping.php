<?php

namespace App\Domain\Planning;

use App\Domain\Shared\ReferenceNumbers;
use App\Enums\PlanStatus;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\MarketScoping;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateMarketScoping
{
    public function __construct(private ReferenceNumbers $numbers) {}

    public function __invoke(User $actor, Department $department, FiscalYear $fiscalYear, string $title, ?string $description = null, ?string $conductedOn = null): MarketScoping
    {
        PlanGuards::requirePermission($actor, 'ppmp.create', $department);
        PlanGuards::requireOpenFiscalYear($fiscalYear, $department->agency_id);

        return DB::transaction(fn () => MarketScoping::create([
            'agency_id' => $department->agency_id,
            'department_id' => $department->id,
            'fiscal_year_id' => $fiscalYear->id,
            'reference_no' => $this->numbers->next($department->agency_id, 'MS', $fiscalYear->year),
            'title' => $title,
            'description' => $description,
            'conducted_by' => $actor->id,
            'conducted_on' => $conductedOn ?? now()->toDateString(),
            'status' => PlanStatus::Draft,
        ]));
    }
}
