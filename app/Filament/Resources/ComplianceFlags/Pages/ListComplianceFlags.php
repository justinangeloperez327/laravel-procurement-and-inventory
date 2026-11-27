<?php

namespace App\Filament\Resources\ComplianceFlags\Pages;

use App\Filament\Resources\ComplianceFlags\ComplianceFlagResource;
use Filament\Resources\Pages\ListRecords;

class ListComplianceFlags extends ListRecords
{
    protected static string $resource = ComplianceFlagResource::class;
}
