<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\RelationManagers\RoleAssignmentsRelationManager;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Access control';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('password')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->rule('min:12')
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText('At least 12 characters. Leave blank to keep the current password.'),
            Select::make('agency_id')->relationship('agency', 'name')->required()->live()
                ->afterStateUpdated(fn ($set) => $set('department_id', null)),
            Select::make('department_id')
                ->relationship('department', 'name', fn (Builder $query, Get $get) => $query->where('agency_id', $get('agency_id')))
                ->searchable()->preload(),
            TextInput::make('employee_number')->maxLength(64),
            TextInput::make('position')->maxLength(255),
            Toggle::make('is_active')->label('Active')->default(true)
                ->disabled(fn (?User $record) => $record !== null && $record->is(Filament::auth()->user()))
                ->helperText('Deactivated users cannot sign in, and lose all role and delegated authority.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['agency', 'department']))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('agency.code')->label('Agency'),
                TextColumn::make('department.name')->label('Department'),
                IconColumn::make('is_admin')->label('Sys admin')->boolean(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([TernaryFilter::make('is_active')->label('Active')])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [RoleAssignmentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
