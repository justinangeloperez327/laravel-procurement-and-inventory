<?php

namespace App\Filament\Resources\Ppmps\Pages;

use App\Filament\Resources\Ppmps\PpmpResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPpmp extends ViewRecord
{
    protected static string $resource = PpmpResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->label('Edit remarks'), ...PpmpResource::planWorkflowActions(), PpmpResource::reviseAction()];
    }
}
