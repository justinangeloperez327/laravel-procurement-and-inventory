<?php

namespace App\Filament\Resources\AccountCodes;

use App\Filament\Resources\AccountCodes\Pages\ManageAccountCodes;
use App\Models\AccountCode;
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

/** Object/account codes (e.g. UACS). Load the current DBM/COA list before production use. */
class AccountCodeResource extends Resource
{
    protected static ?string $model = AccountCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static string|UnitEnum|null $navigationGroup = 'Budget master data';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(32)->unique(ignoreRecord: true),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('classification')->options(['PS' => 'Personnel Services', 'MOOE' => 'Maintenance and Other Operating Expenses', 'CO' => 'Capital Outlay', 'FE' => 'Financial Expenses']),
            Select::make('parent_id')->label('Parent code')
                ->relationship('parent', 'code', fn (Builder $query, ?AccountCode $record) => $query->when($record, fn ($q) => $q->whereKeyNot($record->id)))
                ->searchable()->preload(),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->wrap(),
                TextColumn::make('classification')->badge(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('code')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAccountCodes::route('/')];
    }
}
