<?php

namespace App\Filament\Resources\Items;

use App\Enums\AssetClass;
use App\Filament\Concerns\ScopedToUserAgency;
use App\Filament\Resources\Items\Pages\ManageItems;
use App\Models\Item;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class ItemResource extends Resource
{
    use ScopedToUserAgency;

    protected static ?string $model = Item::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            static::agencySelect(),
            TextInput::make('code')->required()->maxLength(64)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('agency_id', $get('agency_id'))),
            TextInput::make('name')->required()->maxLength(255)->columnSpanFull(),
            Select::make('item_category_id')->label('Category')->relationship('itemCategory', 'name', fn (Builder $query) => $query->where('is_active', true))
                ->required()->searchable()->preload(),
            Select::make('unit_of_measure_id')->label('Unit')->relationship('unitOfMeasure', 'name')->required()->searchable()->preload(),
            Select::make('asset_class')->required()->default(AssetClass::Consumable->value)
                ->options(collect(AssetClass::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
            TextInput::make('reorder_level')->numeric()->minValue(0),
            Textarea::make('description')->columnSpanFull(),
            Textarea::make('specifications')->columnSpanFull(),
            Section::make('Tracking')->columns(4)->columnSpanFull()->schema([
                Toggle::make('is_serialized')->label('Serialized'),
                Toggle::make('is_lot_tracked')->label('Lot tracked'),
                Toggle::make('tracks_expiry')->label('Tracks expiry'),
                Toggle::make('is_cse')->label('Common-use supply (CSE)'),
            ]),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['itemCategory', 'unitOfMeasure']))
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->wrap(),
                TextColumn::make('itemCategory.name')->label('Category'),
                TextColumn::make('unitOfMeasure.code')->label('Unit'),
                TextColumn::make('asset_class')->badge()->formatStateUsing(fn (AssetClass $state) => $state->label()),
                IconColumn::make('is_cse')->label('CSE')->boolean(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('item_category_id')->label('Category')->relationship('itemCategory', 'name'),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->defaultSort('code')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageItems::route('/')];
    }
}
