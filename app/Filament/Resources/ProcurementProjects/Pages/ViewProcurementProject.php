<?php

namespace App\Filament\Resources\ProcurementProjects\Pages;

use App\Filament\Resources\ProcurementProjects\ProcurementProjectResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProcurementProject extends ViewRecord
{
    protected static string $resource = ProcurementProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), ...ProcurementProjectResource::headerActions()];
    }
}
