<?php

namespace App\Filament\Resources\Ppmps\Pages;

use App\Filament\Resources\Ppmps\PpmpResource;
use Filament\Resources\Pages\ListRecords;

class ListPpmps extends ListRecords
{
    protected static string $resource = PpmpResource::class;

    protected function getHeaderActions(): array
    {
        return [PpmpResource::createAction()];
    }
}
