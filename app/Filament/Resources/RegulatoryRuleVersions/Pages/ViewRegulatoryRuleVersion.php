<?php

namespace App\Filament\Resources\RegulatoryRuleVersions\Pages;

use App\Filament\Resources\RegulatoryRuleVersions\RegulatoryRuleVersionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRegulatoryRuleVersion extends ViewRecord
{
    protected static string $resource = RegulatoryRuleVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ...RegulatoryRuleVersionResource::reviewActions(),
        ];
    }
}
