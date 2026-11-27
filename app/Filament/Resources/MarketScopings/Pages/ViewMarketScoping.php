<?php

namespace App\Filament\Resources\MarketScopings\Pages;

use App\Filament\Resources\MarketScopings\MarketScopingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMarketScoping extends ViewRecord
{
    protected static string $resource = MarketScopingResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), ...MarketScopingResource::planWorkflowActions()];
    }
}
