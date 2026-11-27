<?php

namespace App\Filament\Resources\RegulatorySources\Pages;

use App\Filament\Resources\RegulatorySources\RegulatorySourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRegulatorySources extends ManageRecords
{
    protected static string $resource = RegulatorySourceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
