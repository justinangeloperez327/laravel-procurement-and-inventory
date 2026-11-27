<?php

namespace App\Filament\Resources\BudgetAllocations\RelationManagers;

use App\Domain\Shared\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReservationsRelationManager extends RelationManager
{
    protected static string $relationship = 'reservations';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['purchaseRequest', 'reservedBy']))
            ->columns([
                TextColumn::make('reserved_at')->dateTime()->sortable(),
                TextColumn::make('purchaseRequest.reference_no')->label('Purchase request'),
                TextColumn::make('amount')->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'active' ? 'warning' : 'gray'),
                TextColumn::make('reservedBy.name')->label('Certified by'),
                TextColumn::make('release_reason')->placeholder('—')->wrap(),
            ])
            ->defaultSort('id', 'desc');
    }
}
