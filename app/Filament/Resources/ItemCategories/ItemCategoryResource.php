<?php

namespace App\Filament\Resources\ItemCategories;

use App\Enums\AssetClass;
use App\Filament\Resources\ItemCategories\Pages\ManageItemCategories;
use App\Models\ItemCategory;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ItemCategoryResource extends Resource
{
    protected static ?string $model = ItemCategory::class;

    protected static ?string $pluralModelLabel = 'item categories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(32)->unique(ignoreRecord: true),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('parent_id')->label('Parent category')
                ->relationship('parent', 'name', fn (Builder $query, ?ItemCategory $record) => $query->when($record, fn ($q) => $q->whereKeyNot($record->id))),
            Select::make('default_asset_class')->options(collect(AssetClass::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                ->helperText('A starting suggestion only. Final classification follows COA rules at registration.'),
            Select::make('account_code_id')->relationship('accountCode', 'code')->searchable()->preload(),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['parent', 'accountCode']))
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('parent.name')->label('Parent')->placeholder('—'),
                TextColumn::make('default_asset_class')->badge()->formatStateUsing(fn (?AssetClass $state) => $state?->label())->placeholder('—'),
                TextColumn::make('accountCode.code')->label('Account code')->placeholder('—'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('code')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageItemCategories::route('/')];
    }
}
