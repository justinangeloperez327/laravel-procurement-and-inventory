<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Pages\ViewRole;
use App\Models\Role;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Read-only view of the role catalog maintained in App\Domain\Identity\RoleCatalog. */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Access control';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('code'),
            TextEntry::make('name'),
            IconEntry::make('is_read_only')->label('Read-only')->boolean(),
            TextEntry::make('permissions.code')->label('Permissions')->badge()->placeholder('No transactional permissions'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('permissions')->withCount([
                'assignments as active_assignments_count' => fn (Builder $q) => $q->active(),
            ]))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('code')->searchable(),
                TextColumn::make('permissions.code')->label('Permissions')->badge(),
                TextColumn::make('active_assignments_count')->label('Active holders'),
                IconColumn::make('is_read_only')->label('Read-only')->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'view' => ViewRole::route('/{record}'),
        ];
    }
}
