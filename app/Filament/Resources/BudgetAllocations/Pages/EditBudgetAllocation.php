<?php

namespace App\Filament\Resources\BudgetAllocations\Pages;

use App\Filament\Resources\BudgetAllocations\BudgetAllocationResource;
use Filament\Resources\Pages\EditRecord;

class EditBudgetAllocation extends EditRecord
{
    protected static string $resource = BudgetAllocationResource::class;

    protected function getRedirectUrl(): string
    {
        return BudgetAllocationResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
