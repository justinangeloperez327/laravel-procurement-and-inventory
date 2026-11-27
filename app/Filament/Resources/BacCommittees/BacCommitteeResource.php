<?php

namespace App\Filament\Resources\BacCommittees;

use App\Domain\Bac\BacComposition;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Filament\Concerns\ScopedToUserAgency;
use App\Filament\Resources\BacCommittees\Pages\ListBacCommittees;
use App\Filament\Resources\BacCommittees\Pages\ViewBacCommittee;
use App\Filament\Resources\BacCommittees\RelationManagers\MeetingsRelationManager;
use App\Filament\Resources\BacCommittees\RelationManagers\MembersRelationManager;
use App\Filament\Resources\BacCommittees\RelationManagers\ResolutionsRelationManager;
use App\Models\BacCommittee;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class BacCommitteeResource extends Resource
{
    use ReportsDomainViolations, ScopedToUserAgency;

    public const TYPES = ['goods' => 'Goods and services', 'infrastructure' => 'Infrastructure', 'consulting' => 'Consulting services'];

    protected static ?string $model = BacCommittee::class;

    protected static ?string $modelLabel = 'BAC';

    protected static ?string $pluralModelLabel = 'BACs';

    protected static ?string $navigationLabel = 'Bids and Awards Committees';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('committee_type')->label('Type')->formatStateUsing(fn (string $state) => self::TYPES[$state] ?? $state),
            TextEntry::make('office_order_reference')->label('Office order'),
            TextEntry::make('constituted_on')->date(),
            TextEntry::make('dissolved_on')->date()->placeholder('Active'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('members'))
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('committee_type')->label('Type')->formatStateUsing(fn (string $state) => self::TYPES[$state] ?? $state),
                TextColumn::make('office_order_reference')->label('Office order'),
                TextColumn::make('members_count')->label('Members'),
                TextColumn::make('constituted_on')->date(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function constituteAction(): Action
    {
        return Action::make('constitute')
            ->label('Constitute BAC')
            ->authorize(fn () => Filament::auth()->user()?->can('create', BacCommittee::class) ?? false)
            ->schema([
                TextInput::make('name')->required()->maxLength(255),
                Select::make('type')->options(self::TYPES)->required(),
                TextInput::make('office_order')->label('Office order reference')->required()->maxLength(128),
                DatePicker::make('constituted_on')->required()->default(now()),
            ])
            ->action(function (array $data, Action $action, $livewire) {
                $committee = null;
                static::guarded($action, function () use ($data, &$committee) {
                    $committee = app(BacComposition::class)->constitute(Filament::auth()->user(), $data['name'], $data['type'], $data['office_order'], $data['constituted_on']);
                }, 'BAC constituted');
                $livewire->redirect(static::getUrl('view', ['record' => $committee]));
            });
    }

    public static function getRelations(): array
    {
        return [MembersRelationManager::class, MeetingsRelationManager::class, ResolutionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBacCommittees::route('/'),
            'view' => ViewBacCommittee::route('/{record}'),
        ];
    }
}
