<?php

namespace App\Filament\Resources\ProcurementProjects\Pages;

use App\Filament\Resources\ProcurementProjects\ProcurementProjectResource;
use Filament\Resources\Pages\EditRecord;

class EditProcurementProject extends EditRecord
{
    protected static string $resource = ProcurementProjectResource::class;

    protected function getRedirectUrl(): string
    {
        return ProcurementProjectResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
