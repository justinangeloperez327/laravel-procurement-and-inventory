<?php

namespace App\Filament\Resources\Ppmps\Pages;

use App\Filament\Resources\Ppmps\PpmpResource;
use Filament\Resources\Pages\EditRecord;

class EditPpmp extends EditRecord
{
    protected static string $resource = PpmpResource::class;

    protected function getRedirectUrl(): string
    {
        return PpmpResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
