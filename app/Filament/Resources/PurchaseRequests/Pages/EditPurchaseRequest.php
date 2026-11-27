<?php

namespace App\Filament\Resources\PurchaseRequests\Pages;

use App\Filament\Resources\PurchaseRequests\PurchaseRequestResource;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseRequest extends EditRecord
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['app_item_id'] ?? null)) {
            $data['plan_exception_basis'] = null;
            $data['plan_exception_reason'] = null;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return PurchaseRequestResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
