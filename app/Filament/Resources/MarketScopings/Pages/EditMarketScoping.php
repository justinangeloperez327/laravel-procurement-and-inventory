<?php

namespace App\Filament\Resources\MarketScopings\Pages;

use App\Filament\Resources\MarketScopings\MarketScopingResource;
use Filament\Resources\Pages\EditRecord;

class EditMarketScoping extends EditRecord
{
    protected static string $resource = MarketScopingResource::class;

    protected function getRedirectUrl(): string
    {
        return MarketScopingResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
