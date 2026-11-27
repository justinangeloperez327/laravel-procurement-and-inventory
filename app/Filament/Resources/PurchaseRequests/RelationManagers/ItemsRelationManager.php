<?php

namespace App\Filament\Resources\PurchaseRequests\RelationManagers;

use App\Domain\Shared\Money;
use App\Models\Item;
use App\Models\PurchaseRequest;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Request lines; editable only while the request is a draft or returned. Totals are computed by the database. */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function isReadOnly(): bool
    {
        /** @var PurchaseRequest $pr */
        $pr = $this->getOwnerRecord();

        return ! (auth()->user()?->can('update', $pr) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        /** @var PurchaseRequest $pr */
        $pr = $this->getOwnerRecord();

        return $schema->components([
            Select::make('item_id')->label('Catalog item')->searchable()->preload()
                ->relationship('item', 'name', fn (Builder $query) => $query->where('agency_id', $pr->agency_id)->where('is_active', true))
                ->live()
                ->afterStateUpdated(function (?int $state, Set $set) {
                    if ($item = Item::find($state)) {
                        $set('description', $item->name);
                        $set('unit_of_measure_id', $item->unit_of_measure_id);
                        $set('specifications', $item->specifications);
                    }
                }),
            TextInput::make('description')->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('specifications')->columnSpanFull()->helperText('Technical specifications; avoid brand names unless allowed.'),
            Select::make('unit_of_measure_id')->label('Unit')->relationship('unitOfMeasure', 'name')->required()->searchable()->preload(),
            TextInput::make('quantity')->numeric()->required()->gt(0),
            TextInput::make('estimated_unit_cost')->label('Estimated unit cost (₱)')->numeric()->required()->minValue(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('unitOfMeasure'))
            ->columns([
                TextColumn::make('description')->wrap(),
                TextColumn::make('quantity')->numeric(),
                TextColumn::make('unitOfMeasure.code')->label('Unit'),
                TextColumn::make('estimated_unit_cost')->label('Unit cost')->formatStateUsing(fn ($state) => Money::formatUnitCost($state)),
                TextColumn::make('estimated_total')->label('Total')->formatStateUsing(fn ($state) => Money::format($state)),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
