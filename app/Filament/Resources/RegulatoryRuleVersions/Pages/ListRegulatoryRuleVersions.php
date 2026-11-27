<?php

namespace App\Filament\Resources\RegulatoryRuleVersions\Pages;

use App\Filament\Resources\RegulatoryRuleVersions\RegulatoryRuleVersionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRegulatoryRuleVersions extends ListRecords
{
    protected static string $resource = RegulatoryRuleVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
