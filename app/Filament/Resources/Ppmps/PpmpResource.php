<?php

namespace App\Filament\Resources\Ppmps;

use App\Domain\Planning\CreatePpmp;
use App\Domain\Planning\RevisePpmp;
use App\Domain\Shared\Money;
use App\Enums\PlanStatus;
use App\Filament\Concerns\PlanningQueries;
use App\Filament\Concerns\PlanWorkflowActions;
use App\Filament\Resources\Ppmps\Pages\EditPpmp;
use App\Filament\Resources\Ppmps\Pages\ListPpmps;
use App\Filament\Resources\Ppmps\Pages\ViewPpmp;
use App\Filament\Resources\Ppmps\RelationManagers\ItemsRelationManager;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\Ppmp;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class PpmpResource extends Resource
{
    use PlanningQueries, PlanWorkflowActions;

    protected static ?string $model = Ppmp::class;

    protected static ?string $modelLabel = 'PPMP';

    protected static ?string $pluralModelLabel = 'PPMPs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'reference_no';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('remarks')->columnSpanFull()->maxLength(5000),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('reference_no')->label('Reference'),
            TextEntry::make('version'),
            TextEntry::make('status')->badge()->formatStateUsing(fn (PlanStatus $state) => $state->label())
                ->color(fn (PlanStatus $state) => static::planStatusColor($state)),
            TextEntry::make('department.name')->label('Department'),
            TextEntry::make('fiscalYear.year')->label('Fiscal year'),
            TextEntry::make('estimated_total')->label('Estimated budget')
                ->state(fn (Ppmp $record) => Money::format((string) DB::table('ppmp_items')->where('ppmp_id', $record->id)->sum('estimated_budget'))),
            TextEntry::make('preparedBy.name')->label('Prepared by'),
            TextEntry::make('submitted_at')->dateTime()->placeholder('—'),
            TextEntry::make('approvedBy.name')->label('Approved by')->placeholder('—'),
            TextEntry::make('approved_at')->dateTime()->placeholder('—'),
            TextEntry::make('remarks')->columnSpanFull()->placeholder('—'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['department', 'fiscalYear']))
            ->columns([
                TextColumn::make('reference_no')->label('Reference')->searchable()->sortable(),
                TextColumn::make('version'),
                TextColumn::make('department.name')->searchable(),
                TextColumn::make('fiscalYear.year')->label('FY')->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (PlanStatus $state) => $state->label())
                    ->color(fn (PlanStatus $state) => static::planStatusColor($state)),
                TextColumn::make('total_amount')->label('Total (as submitted)')->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(PlanStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                SelectFilter::make('fiscal_year_id')->label('Fiscal year')->relationship('fiscalYear', 'year'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function createAction(): Action
    {
        return Action::make('newPpmp')
            ->label('New PPMP')
            ->authorize(fn () => Filament::auth()->user()?->can('create', Ppmp::class) ?? false)
            ->schema([
                Select::make('department_id')->label('Department')->options(fn () => static::preparableDepartments())->required(),
                Select::make('fiscal_year_id')->label('Fiscal year')->options(fn () => static::openFiscalYears())->required(),
                Textarea::make('remarks')->maxLength(5000),
            ])
            ->action(function (array $data, Action $action, $livewire) {
                $ppmp = null;
                static::guarded($action, function () use ($data, &$ppmp) {
                    $ppmp = app(CreatePpmp::class)(Filament::auth()->user(), Department::findOrFail($data['department_id']),
                        FiscalYear::findOrFail($data['fiscal_year_id']), $data['remarks'] ?? null);
                }, 'PPMP created');
                $livewire->redirect(static::getUrl('view', ['record' => $ppmp]));
            });
    }

    public static function reviseAction(): Action
    {
        return Action::make('revise')
            ->label('Start revision')
            ->icon('heroicon-o-document-duplicate')
            ->visible(fn (Ppmp $record) => $record->status === PlanStatus::Approved
                && (Filament::auth()->user()?->hasPermission('ppmp.create', $record->department) ?? false))
            ->schema([Textarea::make('reason')->required()->maxLength(2000)])
            ->action(function (Ppmp $record, array $data, Action $action, $livewire) {
                $revision = null;
                static::guarded($action, function () use ($record, $data, &$revision) {
                    $revision = app(RevisePpmp::class)(Filament::auth()->user(), $record, $data['reason']);
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
            'index' => ListPpmps::route('/'),
            'view' => ViewPpmp::route('/{record}'),
            'edit' => EditPpmp::route('/{record}/edit'),
        ];
    }
}
