<?php

namespace App\Filament\Resources\MarketScopings;

use App\Domain\Planning\CreateMarketScoping;
use App\Enums\PlanStatus;
use App\Filament\Concerns\PlanningQueries;
use App\Filament\Concerns\PlanWorkflowActions;
use App\Filament\Resources\MarketScopings\Pages\EditMarketScoping;
use App\Filament\Resources\MarketScopings\Pages\ListMarketScopings;
use App\Filament\Resources\MarketScopings\Pages\ViewMarketScoping;
use App\Filament\Resources\MarketScopings\RelationManagers\ItemsRelationManager;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\MarketScoping;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MarketScopingResource extends Resource
{
    use PlanningQueries, PlanWorkflowActions;

    protected static ?string $model = MarketScoping::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlassCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'reference_no';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            DatePicker::make('conducted_on')->required(),
            Textarea::make('description')->columnSpanFull()->maxLength(5000),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('reference_no')->label('Reference'),
            TextEntry::make('status')->badge()->formatStateUsing(fn (PlanStatus $state) => $state->label())
                ->color(fn (PlanStatus $state) => static::planStatusColor($state)),
            TextEntry::make('title'),
            TextEntry::make('department.name')->label('Department'),
            TextEntry::make('fiscalYear.year')->label('Fiscal year'),
            TextEntry::make('conductedBy.name')->label('Conducted by'),
            TextEntry::make('conducted_on')->date(),
            TextEntry::make('approvedBy.name')->label('Reviewed by')->placeholder('—'),
            TextEntry::make('description')->columnSpanFull()->placeholder('—'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['department', 'fiscalYear']))
            ->columns([
                TextColumn::make('reference_no')->label('Reference')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('department.name'),
                TextColumn::make('fiscalYear.year')->label('FY'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (PlanStatus $state) => $state->label())
                    ->color(fn (PlanStatus $state) => static::planStatusColor($state)),
                TextColumn::make('conducted_on')->date()->sortable(),
            ])
            ->defaultSort('conducted_on', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function createAction(): Action
    {
        return Action::make('newMarketScoping')
            ->label('New market scoping')
            ->authorize(fn () => Filament::auth()->user()?->can('create', MarketScoping::class) ?? false)
            ->schema([
                Select::make('department_id')->label('Department')->options(fn () => static::preparableDepartments())->required(),
                Select::make('fiscal_year_id')->label('Fiscal year')->options(fn () => static::openFiscalYears())->required(),
                TextInput::make('title')->required()->maxLength(255),
                DatePicker::make('conducted_on')->required()->default(now()),
                Textarea::make('description')->maxLength(5000),
            ])
            ->action(function (array $data, Action $action, $livewire) {
                $record = null;
                static::guarded($action, function () use ($data, &$record) {
                    $record = app(CreateMarketScoping::class)(Filament::auth()->user(), Department::findOrFail($data['department_id']),
                        FiscalYear::findOrFail($data['fiscal_year_id']), $data['title'], $data['description'] ?? null, $data['conducted_on']);
                }, 'Market scoping created');
                $livewire->redirect(static::getUrl('view', ['record' => $record]));
            });
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketScopings::route('/'),
            'view' => ViewMarketScoping::route('/{record}'),
            'edit' => EditMarketScoping::route('/{record}/edit'),
        ];
    }
}
