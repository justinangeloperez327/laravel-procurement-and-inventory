<?php

namespace App\Filament\Resources\ProcurementProjects\RelationManagers;

use App\Domain\Shared\Money;
use App\Enums\ProcurementMode;
use App\Models\Quotation;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class QuotationsRelationManager extends RelationManager
{
    protected static string $relationship = 'quotations';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->procurementMode !== null && $ownerRecord->procurementMode->code !== ProcurementMode::CompetitiveBidding;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['supplier', 'rfq']))
            ->columns([
                TextColumn::make('rfq.reference_no')->label('RFQ'),
                TextColumn::make('supplier.name')->label('Supplier'),
                TextColumn::make('received_at')->dateTime('M j, Y H:i:s'),
                TextColumn::make('total_amount')->label('Total')->state(fn (Quotation $r) => $r->is_late ? 'Late — not evaluated' : Money::format((string) $r->total_amount)),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('received_at');
    }
}
