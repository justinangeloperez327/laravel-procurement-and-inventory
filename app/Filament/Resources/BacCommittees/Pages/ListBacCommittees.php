<?php

namespace App\Filament\Resources\BacCommittees\Pages;

use App\Filament\Resources\BacCommittees\BacCommitteeResource;
use Filament\Resources\Pages\ListRecords;

class ListBacCommittees extends ListRecords
{
    protected static string $resource = BacCommitteeResource::class;

    protected function getHeaderActions(): array
    {
        return [BacCommitteeResource::constituteAction()];
    }
}
