<?php

namespace App\Filament\Resources\Warehouses;

use App\Filament\Concerns\ScopedToUserAgency;
use App\Filament\Resources\Warehouses\Pages\CreateWarehouse;
use App\Filament\Resources\Warehouses\Pages\EditWarehouse;
use App\Filament\Resources\Warehouses\Pages\ListWarehouses;
use App\Filament\Resources\Warehouses\RelationManagers\LocationsRelationManager;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class WarehouseResource extends Resource
{
    use ScopedToUserAgency;

    protected static ?string $model = Warehouse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            static::agencySelect(),
            TextInput::make('code')->required()->maxLength(32)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('agency_id', $get('agency_id'))),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('department_id')->relationship('department', 'name', fn (Builder $query, Get $get) => $query->where('agency_id', $get('agency_id'))),
            Select::make('custodian_id')->label('Custodian')
                ->relationship('custodian', 'name', fn (Builder $query, Get $get) => $query->where('agency_id', $get('agency_id'))->where('is_active', true))
                ->searchable(),
            Textarea::make('address')->columnSpanFull(),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['department', 'custodian'])->withCount('locations'))
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('department.name')->placeholder('—'),
                TextColumn::make('custodian.name')->placeholder('—'),
                TextColumn::make('locations_count')->label('Locations'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('code')
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [LocationsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouses::route('/'),
            'create' => CreateWarehouse::route('/create'),
            'edit' => EditWarehouse::route('/{record}/edit'),
        ];
    }
}
