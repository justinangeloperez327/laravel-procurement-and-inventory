<?php

namespace App\Filament\Resources\ProcurementProjects\Pages;

use App\Filament\Resources\ProcurementProjects\ProcurementProjectResource;
use Filament\Resources\Pages\ListRecords;

class ListProcurementProjects extends ListRecords
{
    protected static string $resource = ProcurementProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [ProcurementProjectResource::createAction()];
    }
}
