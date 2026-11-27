<?php

namespace App\Filament\Resources\AppVersions;

use App\Domain\Planning\ConsolidatePpmps;
use App\Domain\Planning\CreateApp;
use App\Domain\Planning\ReviseApp;
use App\Domain\Shared\Money;
use App\Enums\PlanStatus;
use App\Filament\Concerns\PlanningQueries;
use App\Filament\Concerns\PlanWorkflowActions;
use App\Filament\Resources\AppVersions\Pages\ListAppVersions;
use App\Filament\Resources\AppVersions\Pages\ViewAppVersion;
use App\Filament\Resources\AppVersions\RelationManagers\ItemsRelationManager;
use App\Models\AppVersion;
use App\Models\FiscalYear;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Annual Procurement Plan versions. Each version is consolidated from approved PPMPs and approved by the HoPE. */
class AppVersionResource extends Resource
{
    use PlanningQueries, PlanWorkflowActions;

    protected static ?string $model = AppVersion::class;

    protected static ?string $modelLabel = 'APP version';

    protected static ?string $navigationLabel = 'Annual Procurement Plan';

    protected static ?string $slug = 'app';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?int $navigationSort = 30;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('app.fiscalYear');
        $user = Filament::auth()->user();

        return ($user instanceof User && ! ($user->is_admin && $user->is_active))
            ? $query->whereHas('app', fn (Builder $q) => $q->where('agency_id', $user->agency_id))
            : $query;
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record ? "{$record->app->reference_no} v{$record->version}" : 'APP';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('app.reference_no')->label('APP'),
            TextEntry::make('version'),
            TextEntry::make('status')->badge()->formatStateUsing(fn (PlanStatus $state) => $state->label())
                ->color(fn (PlanStatus $state) => static::planStatusColor($state)),
            TextEntry::make('app.fiscalYear.year')->label('Fiscal year'),
            TextEntry::make('total_amount')->label('Total estimated budget')->formatStateUsing(fn ($state) => Money::format($state)),
            IconEntry::make('is_indicative')->label('Indicative APP')->boolean(),
            TextEntry::make('preparedBy.name')->label('Prepared by'),
            TextEntry::make('approvedBy.name')->label('Approved by (HoPE)')->placeholder('—'),
            TextEntry::make('approved_at')->dateTime()->placeholder('—'),
            TextEntry::make('supersedes.version')->label('Revises version')->placeholder('—'),
            TextEntry::make('revision_reason')->columnSpanFull()->placeholder('—'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('app.reference_no')->label('APP')->searchable(),
                TextColumn::make('app.fiscalYear.year')->label('FY'),
                TextColumn::make('version'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (PlanStatus $state) => $state->label())
                    ->color(fn (PlanStatus $state) => static::planStatusColor($state)),
                TextColumn::make('total_amount')->label('Total')->formatStateUsing(fn ($state) => Money::format($state)),
                IconColumn::make('is_indicative')->label('Indicative')->boolean(),
                TextColumn::make('approved_at')->dateTime()->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function openAppAction(): Action
    {
        return Action::make('openApp')
            ->label('Open APP for a fiscal year')
            ->authorize(fn () => Filament::auth()->user()?->can('create', AppVersion::class) ?? false)
            ->schema([
                Select::make('fiscal_year_id')->label('Fiscal year')->options(fn () => static::openFiscalYears())->required(),
                Toggle::make('is_indicative')->label('Indicative APP (before budget approval)'),
            ])
            ->action(function (array $data, Action $action, $livewire) {
                $version = null;
                static::guarded($action, function () use ($data, &$version) {
                    $version = app(CreateApp::class)(Filament::auth()->user(), FiscalYear::findOrFail($data['fiscal_year_id']), (bool) $data['is_indicative']);
                }, 'APP opened');
                $livewire->redirect(static::getUrl('view', ['record' => $version]));
            });
    }

    public static function consolidateAction(): Action
    {
        return Action::make('consolidate')
            ->label('Consolidate approved PPMPs')
            ->icon('heroicon-o-arrows-pointing-in')
            ->visible(fn (AppVersion $record) => Filament::auth()->user()?->can('update', $record) ?? false)
            ->requiresConfirmation()
            ->modalDescription('Adds approved PPMP items not yet in this version and drops items whose PPMP was superseded. Budgets are recomputed from the sources.')
            ->action(function (AppVersion $record, Action $action) {
                $result = null;
                static::guarded($action, function () use ($record, &$result) {
                    $result = app(ConsolidatePpmps::class)(Filament::auth()->user(), $record);
                }, 'Consolidated');
                if ($result) {
                    Notification::make()->info()->title("Added {$result['added']} PPMP lines, removed {$result['removed']}; {$result['items']} APP items.")->send();
                }
            });
    }

    public static function reviseAction(): Action
    {
        return Action::make('revise')
            ->label('Start revision')
            ->icon('heroicon-o-document-duplicate')
            ->visible(fn (AppVersion $record) => $record->status === PlanStatus::Approved
                && (Filament::auth()->user()?->hasPermission('app.consolidate') ?? false))
            ->schema([Textarea::make('reason')->required()->maxLength(2000)])
            ->action(function (AppVersion $record, array $data, Action $action, $livewire) {
                $revision = null;
                static::guarded($action, function () use ($record, $data, &$revision) {
                    $revision = app(ReviseApp::class)(Filament::auth()->user(), $record->app, $data['reason']);
                }, 'Revision started');
                $livewire->redirect(static::getUrl('view', ['record' => $revision]));
            });
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppVersions::route('/'),
            'view' => ViewAppVersion::route('/{record}'),
        ];
    }
}
