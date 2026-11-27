<?php

namespace App\Filament\Resources\RegulatoryRuleVersions\Pages;

use App\Enums\RuleStatus;
use App\Filament\Resources\RegulatoryRuleVersions\RegulatoryRuleVersionResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateRegulatoryRuleVersion extends CreateRecord
{
    protected static string $resource = RegulatoryRuleVersionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['prepared_by'] = Filament::auth()->id();
        $data['status'] = RuleStatus::Draft;

        return $data;
    }
}
