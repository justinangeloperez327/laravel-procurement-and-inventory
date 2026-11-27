<?php

namespace App\Filament\Resources\UnitsOfMeasure\Pages;

use App\Filament\Resources\UnitsOfMeasure\UnitOfMeasureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUnitsOfMeasure extends ManageRecords
{
    protected static string $resource = UnitOfMeasureResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
