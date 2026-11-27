<?php

namespace App\Filament\Resources\ProcurementProjects\RelationManagers;

use App\Domain\Shared\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Lines';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['purchaseRequestItem.purchaseRequest', 'purchaseRequestItem.unitOfMeasure', 'procurementLot']))
            ->columns([
                TextColumn::make('purchaseRequestItem.purchaseRequest.reference_no')->label('Purchase request'),
                TextColumn::make('purchaseRequestItem.description')->label('Description')->wrap(),
                TextColumn::make('quantity')->numeric(),
                TextColumn::make('purchaseRequestItem.unitOfMeasure.code')->label('Unit'),
                TextColumn::make('approved_budget')->label('ABC')->formatStateUsing(fn ($state) => Money::format((string) $state)),
                TextColumn::make('procurementLot.lot_no')->label('Lot')->placeholder('—'),
            ]);
    }
}
