<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Domain\Shared\DomainRuleViolation;
use App\Domain\Suppliers\RegisterSupplier;
use App\Filament\Resources\Suppliers\SupplierResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(RegisterSupplier::class)(Filament::auth()->user(), $data);
        } catch (DomainRuleViolation $e) {
            Notification::make()->danger()->title('Supplier not registered')->body($e->getMessage())->send();
            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return SupplierResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
