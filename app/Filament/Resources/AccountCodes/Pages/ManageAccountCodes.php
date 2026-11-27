<?php

namespace App\Filament\Resources\AccountCodes\Pages;

use App\Filament\Resources\AccountCodes\AccountCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAccountCodes extends ManageRecords
{
    protected static string $resource = AccountCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
