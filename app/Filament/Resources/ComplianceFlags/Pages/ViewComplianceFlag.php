<?php

namespace App\Filament\Resources\ComplianceFlags\Pages;

use App\Filament\Resources\ComplianceFlags\ComplianceFlagResource;
use Filament\Resources\Pages\ViewRecord;

class ViewComplianceFlag extends ViewRecord
{
    protected static string $resource = ComplianceFlagResource::class;

    protected function getHeaderActions(): array
    {
        return [ComplianceFlagResource::reviewAction()];
    }
}
