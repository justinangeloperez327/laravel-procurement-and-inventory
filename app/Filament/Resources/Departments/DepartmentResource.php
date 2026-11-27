<?php

namespace App\Filament\Resources\Departments;

use App\Filament\Resources\Departments\Pages\ManageDepartments;
use App\Models\Department;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('agency_id')->relationship('agency', 'name')->required()->live()
                ->disabledOn('edit'),
            Select::make('parent_id')->label('Parent department')
                ->relationship('parent', 'name', fn (Builder $query, Get $get, ?Department $record) => $query
                    ->where('agency_id', $get('agency_id'))
                    ->when($record, fn (Builder $q) => $q->whereKeyNot($record->getKey())))
                ->searchable()->preload(),
            TextInput::make('code')->required()->maxLength(32)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('agency_id', $get('agency_id'))),
            TextInput::make('name')->required()->maxLength(255),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['agency', 'parent']))
            ->columns([
                TextColumn::make('agency.code')->label('Agency')->sortable(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('parent.name')->label('Parent'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([SelectFilter::make('agency')->relationship('agency', 'name')])
            ->defaultSort('code')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDepartments::route('/')];
    }
}
