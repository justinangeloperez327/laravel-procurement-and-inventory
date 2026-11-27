<?php

namespace App\Domain\Requests;

use App\Domain\Planning\PlanGuards;
use App\Domain\Shared\ReferenceNumbers;
use App\Enums\PurchaseRequestStatus;
use App\Models\AppItem;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreatePurchaseRequest
{
    public function __construct(private ReferenceNumbers $numbers) {}

    public function __invoke(User $actor, Department $department, FiscalYear $fiscalYear, string $purpose, ?AppItem $appItem = null): PurchaseRequest
    {
        PlanGuards::requirePermission($actor, 'purchase_request.create', $department);
        PlanGuards::requireOpenFiscalYear($fiscalYear, $department->agency_id);

        return DB::transaction(fn () => PurchaseRequest::create([
            'agency_id' => $department->agency_id,
            'department_id' => $department->id,
            'fiscal_year_id' => $fiscalYear->id,
            'reference_no' => $this->numbers->next($department->agency_id, 'PR', $fiscalYear->year),
            'purpose' => $purpose,
            'requested_by' => $actor->id,
            'status' => PurchaseRequestStatus::Draft,
            'total_amount' => 0,
            'app_item_id' => $appItem?->id,
        ]));
    }
}
