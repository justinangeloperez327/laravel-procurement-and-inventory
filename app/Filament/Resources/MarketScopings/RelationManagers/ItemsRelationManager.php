<?php

namespace App\Filament\Resources\MarketScopings\RelationManagers;

use App\Domain\Shared\Money;
use App\Models\MarketScoping;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Scoped items and prices';

    public function isReadOnly(): bool
    {
        /** @var MarketScoping $scoping */
        $scoping = $this->getOwnerRecord();

        return ! (auth()->user()?->can('update', $scoping) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('item_id')->label('Catalog item')->searchable()->preload()
                ->relationship('item', 'name', fn (Builder $query) => $query->where('agency_id', $this->getOwnerRecord()->agency_id)),
            TextInput::make('description')->required()->maxLength(255),
            Select::make('unit_of_measure_id')->label('Unit')->relationship('unitOfMeasure', 'name'),
            TextInput::make('estimated_unit_cost')->label('Indicative unit price (₱)')->numeric()->required()->minValue(0),
            TextInput::make('price_source')->maxLength(128)->placeholder('e.g. canvass of 3 suppliers, PhilGEPS price data'),
            TextInput::make('sources_count')->label('Number of sources')->integer()->minValue(0),
            Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')->wrap(),
                TextColumn::make('estimated_unit_cost')->label('Unit price')->formatStateUsing(fn ($state) => Money::formatUnitCost($state)),
                TextColumn::make('price_source')->placeholder('—'),
                TextColumn::make('sources_count')->label('Sources')->placeholder('—'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
