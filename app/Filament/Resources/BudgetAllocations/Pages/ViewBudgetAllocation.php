<?php

namespace App\Filament\Resources\BudgetAllocations\Pages;

use App\Filament\Resources\BudgetAllocations\BudgetAllocationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBudgetAllocation extends ViewRecord
{
    protected static string $resource = BudgetAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), ...BudgetAllocationResource::ledgerActions()];
    }
}
