<?php

namespace App\Filament\Resources\FundSources\Pages;

use App\Filament\Resources\FundSources\FundSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFundSources extends ManageRecords
{
    protected static string $resource = FundSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
