<?php

namespace App\Filament\Resources\UnitsOfMeasure;

use App\Filament\Resources\UnitsOfMeasure\Pages\ManageUnitsOfMeasure;
use App\Models\UnitOfMeasure;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UnitOfMeasureResource extends Resource
{
    protected static ?string $model = UnitOfMeasure::class;

    protected static ?string $slug = 'units-of-measure';

    protected static ?string $modelLabel = 'unit of measure';

    protected static ?string $pluralModelLabel = 'units of measure';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(16)->unique(ignoreRecord: true),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('base_unit_id')->label('Base unit')
                ->relationship('baseUnit', 'name', fn (Builder $query, ?UnitOfMeasure $record) => $query->when($record, fn ($q) => $q->whereKeyNot($record->id)))
                ->live(),
            TextInput::make('conversion_factor')->numeric()->required()->default(1)->gt(0)
                ->helperText('How many base units make one of this unit.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('baseUnit.code')->label('Base unit')->placeholder('—'),
                TextColumn::make('conversion_factor')->numeric(),
            ])
            ->defaultSort('code')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUnitsOfMeasure::route('/')];
    }
}
