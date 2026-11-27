<?php

namespace App\Domain\Planning;

use App\Domain\Shared\ReferenceNumbers;
use App\Enums\PlanStatus;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\Ppmp;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreatePpmp
{
    public function __construct(private ReferenceNumbers $numbers) {}

    public function __invoke(User $actor, Department $department, FiscalYear $fiscalYear, ?string $remarks = null): Ppmp
    {
        PlanGuards::requirePermission($actor, 'ppmp.create', $department);
        PlanGuards::requireOpenFiscalYear($fiscalYear, $department->agency_id);

        return DB::transaction(fn () => Ppmp::create([
            'agency_id' => $department->agency_id,
            'department_id' => $department->id,
            'fiscal_year_id' => $fiscalYear->id,
            'reference_no' => $this->numbers->next($department->agency_id, 'PPMP', $fiscalYear->year),
            'version' => 1,
            'status' => PlanStatus::Draft,
            'total_amount' => 0,
            'prepared_by' => $actor->id,
            'remarks' => $remarks,
        ]));
    }
}
