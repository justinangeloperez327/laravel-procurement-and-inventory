<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\Suppliers\SupplierResource;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('tin', $data) && $data['tin'] !== null) {
            $data['tin'] = preg_replace('/\D/', '', $data['tin']) ?: null;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return SupplierResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
