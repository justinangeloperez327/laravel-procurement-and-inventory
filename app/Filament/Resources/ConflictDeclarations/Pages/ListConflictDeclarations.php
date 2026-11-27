<?php

namespace App\Filament\Resources\ConflictDeclarations\Pages;

use App\Filament\Resources\ConflictDeclarations\ConflictDeclarationResource;
use Filament\Resources\Pages\ListRecords;

class ListConflictDeclarations extends ListRecords
{
    protected static string $resource = ConflictDeclarationResource::class;

    protected function getHeaderActions(): array
    {
        return [ConflictDeclarationResource::declareAction()];
    }
}
