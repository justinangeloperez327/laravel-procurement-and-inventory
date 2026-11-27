<?php

namespace App\Filament\Resources\MarketScopings\Pages;

use App\Filament\Resources\MarketScopings\MarketScopingResource;
use Filament\Resources\Pages\ListRecords;

class ListMarketScopings extends ListRecords
{
    protected static string $resource = MarketScopingResource::class;

    protected function getHeaderActions(): array
    {
        return [MarketScopingResource::createAction()];
    }
}
