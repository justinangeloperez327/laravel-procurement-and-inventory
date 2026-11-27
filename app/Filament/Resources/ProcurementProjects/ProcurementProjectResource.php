<?php

namespace App\Filament\Resources\ProcurementProjects;

use App\Domain\Bidding\Evaluation;
use App\Domain\Bidding\Offer;
use App\Domain\Bidding\PostQualifications;
use App\Domain\Bidding\Recommendation;
use App\Domain\Procurement\CreateProcurementProject;
use App\Domain\Procurement\ModeScreening;
use App\Domain\Procurement\ModeSelection;
use App\Domain\Procurement\ProjectSchedule;
use App\Domain\Shared\Money;
use App\Enums\ProcurementCategory;
use App\Enums\ProcurementMode;
use App\Enums\ProcurementProjectStatus;
use App\Enums\PurchaseRequestStatus;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Filament\Concerns\ScopedToUserAgency;
use App\Filament\Resources\ProcurementProjects\Pages\EditProcurementProject;
use App\Filament\Resources\ProcurementProjects\Pages\ListProcurementProjects;
use App\Filament\Resources\ProcurementProjects\Pages\ViewProcurementProject;
use App\Filament\Resources\ProcurementProjects\RelationManagers\BiddingsRelationManager;
use App\Filament\Resources\ProcurementProjects\RelationManagers\BidsRelationManager;
use App\Filament\Resources\ProcurementProjects\RelationManagers\CriteriaRelationManager;
use App\Filament\Resources\ProcurementProjects\RelationManagers\DeadlinesRelationManager;
use App\Filament\Resources\ProcurementProjects\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\ProcurementProjects\RelationManagers\JustificationsRelationManager;
use App\Filament\Resources\ProcurementProjects\RelationManagers\LotsRelationManager;
use App\Filament\Resources\ProcurementProjects\RelationManagers\PostQualificationsRelationManager;
use App\Filament\Resources\ProcurementProjects\RelationManagers\ProtestsRelationManager;
use App\Filament\Resources\ProcurementProjects\RelationManagers\PublicationsRelationManager;
use App\Filament\Resources\ProcurementProjects\RelationManagers\QuotationsRelationManager;
use App\Filament\Resources\ProcurementProjects\RelationManagers\RfqsRelationManager;
use App\Models\BacResolution;
use App\Models\Bid;
use App\Models\EvaluationCriterion;
use App\Models\FiscalYear;
use App\Models\ProcurementProject;
use App\Models\PurchaseRequestItem;
use App\Models\Quotation;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ProcurementProjectResource extends Resource
{
    use ReportsDomainViolations, ScopedToUserAgency;

    protected static ?string $model = ProcurementProject::class;

    protected static ?string $modelLabel = 'procurement project';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'reference_no';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('description')->columnSpanFull()->maxLength(5000),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('reference_no')->label('Reference'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (ProcurementProjectStatus $state) => $state->label())
                    ->color(fn (ProcurementProjectStatus $state) => static::statusColor($state)),
                TextEntry::make('approved_budget')->label('ABC')->formatStateUsing(fn ($state) => Money::format((string) $state)),
                TextEntry::make('title')->columnSpan(2),
                TextEntry::make('category')->formatStateUsing(fn (ProcurementCategory $state) => $state->label()),
                TextEntry::make('procurementMode.name')->label('Procurement mode')->placeholder('Not yet decided'),
                TextEntry::make('appItem.code')->label('APP item')->placeholder('—'),
                TextEntry::make('endUserDepartment.name')->label('End user')->placeholder('Several departments'),
                TextEntry::make('procurementOfficer.name')->label('Procurement officer'),
                TextEntry::make('fiscalYear.year')->label('Fiscal year'),
                TextEntry::make('legal_regime')->label('Legal regime'),
                TextEntry::make('description')->columnSpanFull()->placeholder('—'),
            ]),
            Section::make('Evaluation ranking')->columnSpanFull()
                ->visible(fn (ProcurementProject $record) => in_array($record->status, [ProcurementProjectStatus::Evaluation, ProcurementProjectStatus::PostQualification,
                    ProcurementProjectStatus::Recommended, ProcurementProjectStatus::Awarded], true))
                ->schema([
                    TextEntry::make('ranking')->hiddenLabel()->listWithLineBreaks()
                        ->state(fn (ProcurementProject $record) => app(Evaluation::class)->rank($record)->values()->map(fn (Offer $o, int $i) => sprintf(
                            '%d. %s — %s — %s', $i + 1, $o->supplier()->name, Money::format($o->amount()),
                            ! $o->complete ? 'evaluation in progress' : ($o->passed ? 'passed' : 'failed'),
                        ))->all() ?: ['No offers.']),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['procurementMode', 'endUserDepartment']))
            ->columns([
                TextColumn::make('reference_no')->label('Reference')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('procurementMode.name')->label('Mode')->placeholder('—')->wrap(),
                TextColumn::make('approved_budget')->label('ABC')->formatStateUsing(fn ($state) => Money::format((string) $state)),
                TextColumn::make('status')->badge()->formatStateUsing(fn (ProcurementProjectStatus $state) => $state->label())
                    ->color(fn (ProcurementProjectStatus $state) => static::statusColor($state)),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(ProcurementProjectStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function statusColor(ProcurementProjectStatus $status): string
    {
        return match ($status) {
            ProcurementProjectStatus::Proposed, ProcurementProjectStatus::ModeReview => 'warning',
            ProcurementProjectStatus::Failed, ProcurementProjectStatus::Cancelled => 'danger',
            ProcurementProjectStatus::Completed => 'gray',
            default => 'info',
        };
    }

    /** @return array<int, string> Lines of approved requests not in an active project. */
    public static function availableLines(int $agencyId, ?int $fiscalYearId): array
    {
        if ($fiscalYearId === null) {
            return [];
        }

        return PurchaseRequestItem::query()
            ->with('purchaseRequest')
            ->whereHas('purchaseRequest', fn (Builder $query) => $query->where('agency_id', $agencyId)->where('fiscal_year_id', $fiscalYearId)
                ->whereIn('status', [PurchaseRequestStatus::Approved, PurchaseRequestStatus::Procurement]))
            ->get()
            ->reject(fn (PurchaseRequestItem $line) => CreateProcurementProject::lineInActiveProject($line->id))
            ->mapWithKeys(fn (PurchaseRequestItem $line) => [$line->id => "{$line->purchaseRequest->reference_no} — {$line->description} (".Money::format((string) $line->estimated_total).')'])
            ->all();
    }

    public static function createAction(): Action
    {
        $user = fn (): ?User => Filament::auth()->user();

        return Action::make('newProject')
            ->label('New procurement project')
            ->authorize(fn () => $user()?->can('create', ProcurementProject::class) ?? false)
            ->schema([
                Select::make('fiscal_year_id')->label('Fiscal year')->required()->live()
                    ->options(fn () => FiscalYear::where('agency_id', $user()->agency_id)->orderByDesc('year')->pluck('year', 'id')),
                TextInput::make('title')->required()->maxLength(255),
                Select::make('category')->required()->options(collect(ProcurementCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                Select::make('lines')->label('Approved purchase request lines')->multiple()->required()->searchable()
                    ->options(fn (Get $get) => static::availableLines($user()->agency_id, $get('fiscal_year_id'))),
                Textarea::make('description')->maxLength(5000),
            ])
            ->action(function (array $data, Action $action, $livewire) use ($user) {
                $project = null;
                static::guarded($action, function () use ($data, $user, &$project) {
                    $project = app(CreateProcurementProject::class)($user(), FiscalYear::findOrFail($data['fiscal_year_id']), $data['title'],
                        ProcurementCategory::from($data['category']), $data['lines'], $data['description'] ?? null);
                }, 'Procurement project created');
                $livewire->redirect(static::getUrl('view', ['record' => $project]));
            });
    }

    /** @return list<Action> */
    public static function headerActions(): array
    {
        $user = fn (): ?User => Filament::auth()->user();

        return [
            Action::make('proposeMode')
                ->label('Propose procurement mode')
                ->icon('heroicon-o-scale')
                ->visible(fn (ProcurementProject $record) => $record->status === ProcurementProjectStatus::Proposed && ($user()?->hasPermission('procurement.manage') ?? false))
                ->schema(fn (ProcurementProject $record) => [
                    Select::make('mode')->required()->live()
                        ->options(collect(ProcurementMode::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                    TextEntry::make('screening')->label('Screening against approved rules')
                        ->state(function (Get $get) use ($record) {
                            if (blank($get('mode'))) {
                                return ['Choose a mode to see the screening.'];
                            }
                            $result = app(ModeScreening::class)->screen($record, ProcurementMode::from($get('mode')), $get('evidence') ?? []);

                            return $result->checks === [] ? ['No rules apply; competitive bidding is the default method.']
                                : array_map(fn ($c) => ($c['passed'] ? '✓ ' : ($c['blocking'] ? '✗ ' : '! ')).$c['rule_code'].': '.$c['message'], $result->checks);
                        })
                        ->listWithLineBreaks(),
                    Textarea::make('justification')->required()->maxLength(5000),
                    KeyValue::make('evidence')->keyLabel('Evidence (e.g. canvass)')->valueLabel('Reference / description')->live(onBlur: true),
                ])
                ->action(function (ProcurementProject $record, array $data, Action $action) use ($user) {
                    static::guarded($action, fn () => app(ModeSelection::class)->propose($user(), $record, ProcurementMode::from($data['mode']),
                        $data['justification'], $data['evidence'] ?? []), 'Mode proposed for BAC review');
                    $record->refresh();
                }),
            Action::make('evaluate')
                ->label('Record evaluation')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (ProcurementProject $record) => $record->status === ProcurementProjectStatus::Evaluation && ($user()?->hasPermission('bid.evaluate') ?? false))
                ->schema(fn (ProcurementProject $record) => [
                    Select::make('offer')->label('Offer')->required()
                        ->options(Evaluation::offers($record)->mapWithKeys(fn ($o) => [($o instanceof Bid ? 'bid:' : 'quotation:').$o->id => $o->supplier->name.' — '.Money::format((string) ($o instanceof Bid ? $o->bid_amount : $o->total_amount))])),
                    Select::make('criterion_id')->label('Criterion')->required()->live()
                        ->options($record->criteria()->whereNotNull('locked_at')->pluck('criterion', 'id')),
                    Select::make('result')->options(['pass' => 'Pass', 'fail' => 'Fail'])
                        ->visible(fn (Get $get) => EvaluationCriterion::find($get('criterion_id'))?->criterion_type === 'pass_fail')
                        ->required(fn (Get $get) => EvaluationCriterion::find($get('criterion_id'))?->criterion_type === 'pass_fail'),
                    TextInput::make('score')->numeric()->minValue(0)->maxValue(100)
                        ->visible(fn (Get $get) => EvaluationCriterion::find($get('criterion_id'))?->criterion_type === 'rated'),
                    Textarea::make('remarks'),
                ])
                ->action(function (ProcurementProject $record, array $data, Action $action) use ($user) {
                    [$type, $id] = explode(':', $data['offer']);
                    $offer = $type === 'bid' ? Bid::findOrFail($id) : Quotation::findOrFail($id);
                    static::guarded($action, fn () => app(Evaluation::class)->evaluate($user(), $offer, EvaluationCriterion::findOrFail($data['criterion_id']),
                        $data['result'] ?? 'score', isset($data['score']) ? (string) $data['score'] : null, $data['remarks'] ?? null), 'Evaluation recorded');
                }),
            Action::make('finishEvaluation')
                ->label('Finish evaluation')
                ->icon('heroicon-o-flag')
                ->visible(fn (ProcurementProject $record) => $record->status === ProcurementProjectStatus::Evaluation && ($user()?->hasPermission('bac.recommend') ?? false))
                ->requiresConfirmation()
                ->action(function (ProcurementProject $record, Action $action) use ($user) {
                    static::guarded($action, fn () => app(Evaluation::class)->finish($user(), $record), 'Evaluation completed');
                    $record->refresh();
                }),
            Action::make('startPostQualification')
                ->label('Post-qualify next offer')
                ->icon('heroicon-o-magnifying-glass')
                ->visible(fn (ProcurementProject $record) => $record->status === ProcurementProjectStatus::PostQualification
                    && ($user()?->hasPermission('bid.evaluate') ?? false) && ! $record->postQualifications()->whereIn('result', ['pending', 'passed'])->exists())
                ->requiresConfirmation()
                ->action(fn (ProcurementProject $record, Action $action) => static::guarded($action, fn () => app(PostQualifications::class)->startNext($user(), $record), 'Post-qualification started')),
            Action::make('recommendAward')
                ->label('Recommend award')
                ->icon('heroicon-o-trophy')->color('success')
                ->visible(fn (ProcurementProject $record) => $record->status === ProcurementProjectStatus::PostQualification
                    && ($user()?->hasPermission('bac.recommend') ?? false) && $record->postQualifications()->where('result', 'passed')->exists())
                ->schema(fn (ProcurementProject $record) => [
                    Select::make('resolution_id')->label('BAC resolution recommending award')->required()
                        ->options(BacResolution::where('procurement_project_id', $record->id)->where('resolution_type', 'recommend_award')->pluck('resolution_no', 'id')),
                ])
                ->action(function (ProcurementProject $record, array $data, Action $action) use ($user) {
                    static::guarded($action, fn () => app(Recommendation::class)->recommendAward($user(), $record, BacResolution::findOrFail($data['resolution_id'])), 'Award recommended');
                    $record->refresh();
                }),
            Action::make('declareFailure')
                ->label('Declare failure')
                ->icon('heroicon-o-no-symbol')->color('danger')
                ->visible(fn (ProcurementProject $record) => in_array($record->status, [ProcurementProjectStatus::Solicitation, ProcurementProjectStatus::Receipt,
                    ProcurementProjectStatus::Evaluation, ProcurementProjectStatus::PostQualification, ProcurementProjectStatus::Recommended], true)
                    && ($user()?->hasPermission('bac.recommend') ?? false))
                ->schema(fn (ProcurementProject $record) => [
                    Select::make('resolution_id')->label('BAC resolution declaring failure')->required()
                        ->options(BacResolution::where('procurement_project_id', $record->id)->where('resolution_type', 'declare_failure')->pluck('resolution_no', 'id')),
                ])
                ->requiresConfirmation()
                ->action(function (ProcurementProject $record, array $data, Action $action) use ($user) {
                    static::guarded($action, fn () => app(Recommendation::class)->declareFailure($user(), $record, BacResolution::findOrFail($data['resolution_id'])), 'Failure declared');
                    $record->refresh();
                }),
            Action::make('cancelProject')
                ->label('Cancel project')
                ->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn (ProcurementProject $record) => ! in_array($record->status, [...CreateProcurementProject::INACTIVE, ProcurementProjectStatus::Completed], true)
                    && ($user()?->hasPermission(in_array($record->status, [ProcurementProjectStatus::Proposed, ProcurementProjectStatus::ModeReview], true)
                        ? 'procurement.manage' : 'procurement_mode.approve') ?? false))
                ->requiresConfirmation()
                ->schema([Textarea::make('reason')->required()->maxLength(2000)])
                ->action(function (ProcurementProject $record, array $data, Action $action) use ($user) {
                    static::guarded($action, fn () => app(ProjectSchedule::class)->cancel($user(), $record, $data['reason']), 'Project cancelled');
                    $record->refresh();
                }),
        ];
    }

    public static function getRelations(): array
    {
        return [
            JustificationsRelationManager::class,
            ItemsRelationManager::class,
            LotsRelationManager::class,
            CriteriaRelationManager::class,
            RfqsRelationManager::class,
            QuotationsRelationManager::class,
            BiddingsRelationManager::class,
            BidsRelationManager::class,
            PostQualificationsRelationManager::class,
            ProtestsRelationManager::class,
            DeadlinesRelationManager::class,
            PublicationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProcurementProjects::route('/'),
            'view' => ViewProcurementProject::route('/{record}'),
            'edit' => EditProcurementProject::route('/{record}/edit'),
        ];
    }
}
