<?php

namespace App\Filament\Resources\BudgetAllocations\Pages;

use App\Domain\Budget\CreateBudgetAllocation as CreateAllocation;
use App\Domain\Shared\DomainRuleViolation;
use App\Filament\Resources\BudgetAllocations\BudgetAllocationResource;
use App\Models\AccountCode;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\FundSource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateBudgetAllocation extends CreateRecord
{
    protected static string $resource = BudgetAllocationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateAllocation::class)(
                Filament::auth()->user(),
                FiscalYear::findOrFail($data['fiscal_year_id']),
                FundSource::findOrFail($data['fund_source_id']),
                AccountCode::findOrFail($data['account_code_id']),
                (string) $data['amount'],
                $data['authorization_reference'],
                filled($data['department_id'] ?? null) ? Department::findOrFail($data['department_id']) : null,
            );
        } catch (DomainRuleViolation $e) {
            Notification::make()->danger()->title('Allotment not recorded')->body($e->getMessage())->send();
            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return BudgetAllocationResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
