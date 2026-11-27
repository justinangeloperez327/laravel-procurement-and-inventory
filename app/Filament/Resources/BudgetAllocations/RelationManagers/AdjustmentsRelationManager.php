<?php

namespace App\Filament\Resources\BudgetAllocations\RelationManagers;

use App\Domain\Shared\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Read-only view of the append-only adjustment ledger. */
class AdjustmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'adjustments';

    protected static ?string $title = 'Adjustment ledger';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('approvedBy'))
            ->columns([
                TextColumn::make('approved_at')->label('Recorded')->dateTime()->sortable(),
                TextColumn::make('adjustment_type')->label('Type')->badge()->formatStateUsing(fn (string $state) => str_replace('_', ' ', ucfirst($state))),
                TextColumn::make('amount')->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('authorization_reference')->label('Authorization'),
                TextColumn::make('reason')->wrap()->limit(60),
                TextColumn::make('approvedBy.name')->label('By'),
            ])
            ->defaultSort('id', 'desc');
    }
}
