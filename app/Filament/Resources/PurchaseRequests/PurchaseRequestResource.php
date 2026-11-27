<?php

namespace App\Filament\Resources\PurchaseRequests;

use App\Domain\Approvals\ApprovalEngine;
use App\Domain\Budget\BudgetLedger;
use App\Domain\Identity\Authorizer;
use App\Domain\Planning\AppCoverage;
use App\Domain\Requests\CreatePurchaseRequest;
use App\Domain\Requests\PurchaseRequestAccess;
use App\Domain\Requests\PurchaseRequestWorkflow;
use App\Domain\Shared\Money;
use App\Enums\PurchaseRequestStatus;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Filament\Resources\PurchaseRequests\Pages\EditPurchaseRequest;
use App\Filament\Resources\PurchaseRequests\Pages\ListPurchaseRequests;
use App\Filament\Resources\PurchaseRequests\Pages\ViewPurchaseRequest;
use App\Filament\Resources\PurchaseRequests\RelationManagers\ItemsRelationManager;
use App\Models\AppItem;
use App\Models\BudgetAllocation;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\PurchaseRequest;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
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

class PurchaseRequestResource extends Resource
{
    use ReportsDomainViolations;

    protected static ?string $model = PurchaseRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'reference_no';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }
        if (! ($user->is_admin && $user->is_active)) {
            $query->where('purchase_requests.agency_id', $user->agency_id);
        }
        $departments = PurchaseRequestAccess::departmentIds($user);

        return $departments === null ? $query : $query->whereIn('purchase_requests.department_id', $departments);
    }

    /** @return array<int, string> Items of the approved APP for the fiscal year. */
    public static function appItemOptions(int $agencyId, ?int $fiscalYearId): array
    {
        if ($fiscalYearId === null) {
            return [];
        }

        return AppCoverage::usableItems($agencyId, $fiscalYearId)->orderBy('code')->get()
            ->mapWithKeys(fn (AppItem $i) => [$i->id => "{$i->code} — {$i->description} (".Money::format((string) $i->estimated_budget).')'])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('purpose')->required()->maxLength(5000)->columnSpanFull(),
            Select::make('app_item_id')->label('APP item')->searchable()
                ->options(fn (PurchaseRequest $record) => static::appItemOptions($record->agency_id, $record->fiscal_year_id))
                ->live()
                ->helperText('Leave empty only for a documented plan exception.'),
            Select::make('fund_source_id')->relationship('fundSource', 'name', fn (Builder $query, PurchaseRequest $record) => $query->where('agency_id', $record->agency_id)),
            Select::make('account_code_id')->relationship('accountCode', 'code')->searchable()->preload(),
            Section::make('Plan exception')->columnSpanFull()->visible(fn (Get $get) => blank($get('app_item_id')))
                ->description('A request outside the approved APP needs a lawful basis and is reviewed for compliance before funds are certified.')
                ->schema([
                    TextInput::make('plan_exception_basis')->label('Legal basis')->maxLength(255),
                    Textarea::make('plan_exception_reason')->label('Reason')->maxLength(5000),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('reference_no')->label('Reference'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (PurchaseRequestStatus $state) => $state->label())
                    ->color(fn (PurchaseRequestStatus $state) => static::statusColor($state)),
                TextEntry::make('total')->label('Estimated total')
                    ->state(fn (PurchaseRequest $record) => Money::format((string) $record->items()->sum('estimated_total'))),
                TextEntry::make('department.name')->label('Department'),
                TextEntry::make('fiscalYear.year')->label('Fiscal year'),
                TextEntry::make('requestedBy.name')->label('Requested by'),
                TextEntry::make('appItem.code')->label('APP item')->placeholder('Plan exception'),
                TextEntry::make('next_step')->label('Awaiting')
                    ->state(fn (PurchaseRequest $record) => in_array($record->status, [PurchaseRequestStatus::DepartmentReview, PurchaseRequestStatus::BudgetReview], true)
                        ? app(ApprovalEngine::class)->state($record)->nextStep?->name : null)
                    ->placeholder('—'),
                TextEntry::make('reservation')->label('Funds reserved')
                    ->state(fn (PurchaseRequest $record) => ($r = $record->activeReservation())
                        ? Money::format((string) $r->amount).' from '.$r->budgetAllocation->reference_no : null)
                    ->placeholder('—'),
                TextEntry::make('purpose')->columnSpanFull(),
                TextEntry::make('plan_exception_basis')->label('Plan exception basis')->visible(fn (PurchaseRequest $record) => $record->hasPlanException()),
                TextEntry::make('plan_exception_reason')->label('Plan exception reason')->visible(fn (PurchaseRequest $record) => $record->hasPlanException()),
                TextEntry::make('cancellation_reason')->visible(fn (PurchaseRequest $record) => filled($record->cancellation_reason)),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['department', 'requestedBy']))
            ->columns([
                TextColumn::make('reference_no')->label('Reference')->searchable()->sortable(),
                TextColumn::make('purpose')->limit(50)->wrap()->searchable(),
                TextColumn::make('department.name'),
                TextColumn::make('requestedBy.name')->label('Requested by'),
                TextColumn::make('total_amount')->label('Total')->formatStateUsing(fn ($state) => Money::format((string) $state)),
                TextColumn::make('status')->badge()->formatStateUsing(fn (PurchaseRequestStatus $state) => $state->label())
                    ->color(fn (PurchaseRequestStatus $state) => static::statusColor($state)),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(PurchaseRequestStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function statusColor(PurchaseRequestStatus $status): string
    {
        return match ($status) {
            PurchaseRequestStatus::Draft => 'gray',
            PurchaseRequestStatus::Submitted, PurchaseRequestStatus::DepartmentReview, PurchaseRequestStatus::BudgetReview => 'info',
            PurchaseRequestStatus::Returned => 'warning',
            PurchaseRequestStatus::Approved, PurchaseRequestStatus::Procurement, PurchaseRequestStatus::Fulfilled => 'success',
            PurchaseRequestStatus::Rejected, PurchaseRequestStatus::Cancelled => 'danger',
        };
    }

    public static function createAction(): Action
    {
        $user = fn (): ?User => Filament::auth()->user();

        return Action::make('newRequest')
            ->label('New purchase request')
            ->authorize(fn () => $user()?->can('create', PurchaseRequest::class) ?? false)
            ->schema([
                Select::make('department_id')->label('Department')->required()
                    ->options(function () use ($user) {
                        $scope = app(Authorizer::class)->departmentScope($user(), 'purchase_request.create');

                        return Department::where('agency_id', $user()->agency_id)->where('is_active', true)
                            ->when($scope !== null, fn (Builder $q) => $q->whereKey($scope))->orderBy('name')->pluck('name', 'id');
                    }),
                Select::make('fiscal_year_id')->label('Fiscal year')->required()->live()
                    ->options(fn () => FiscalYear::where('agency_id', $user()->agency_id)->where('status', FiscalYear::STATUS_OPEN)->orderByDesc('year')->pluck('year', 'id')),
                Select::make('app_item_id')->label('APP item')->searchable()
                    ->options(fn (Get $get) => static::appItemOptions($user()->agency_id, $get('fiscal_year_id')))
                    ->helperText('The approved APP item that covers this purchase.'),
                Textarea::make('purpose')->required()->maxLength(5000),
            ])
            ->action(function (array $data, Action $action, $livewire) use ($user) {
                $pr = null;
                static::guarded($action, function () use ($data, $user, &$pr) {
                    $pr = app(CreatePurchaseRequest::class)($user(), Department::findOrFail($data['department_id']),
                        FiscalYear::findOrFail($data['fiscal_year_id']), $data['purpose'],
                        filled($data['app_item_id'] ?? null) ? AppItem::findOrFail($data['app_item_id']) : null);
                }, 'Purchase request created');
                $livewire->redirect(static::getUrl('view', ['record' => $pr]));
            });
    }

    /** @return list<Action> */
    public static function workflowActions(): array
    {
        $user = fn (): ?User => Filament::auth()->user();
        $flow = fn () => app(PurchaseRequestWorkflow::class);
        $underReview = fn (PurchaseRequest $r) => in_array($r->status, [PurchaseRequestStatus::DepartmentReview, PurchaseRequestStatus::BudgetReview], true);
        $canAct = fn (PurchaseRequest $r) => $user() !== null && $underReview($r) && app(ApprovalEngine::class)->canAct($user(), $r);
        $atBudget = fn (PurchaseRequest $r) => app(ApprovalEngine::class)->state($r)->nextStep?->permission_code === PurchaseRequestWorkflow::BUDGET_PERMISSION;
        $refresh = fn (PurchaseRequest $r) => $r->refresh();

        return [
            Action::make('submit')
                ->label('Submit')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn (PurchaseRequest $record) => $record->isEditable() && ($user()?->hasPermission('purchase_request.submit', $record->department) ?? false))
                ->requiresConfirmation()
                ->modalDescription('The request is checked against the approved APP and locked for review.')
                ->action(function (PurchaseRequest $record, Action $action) use ($flow, $user, $refresh) {
                    static::guarded($action, fn () => $flow()->submit($user(), $record), 'Submitted for approval');
                    $refresh($record);
                }),
            Action::make('approve')
                ->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn (PurchaseRequest $record) => $canAct($record) && ! $atBudget($record))
                ->requiresConfirmation()
                ->schema([Textarea::make('remarks')->maxLength(2000)])
                ->action(function (PurchaseRequest $record, array $data, Action $action) use ($flow, $user, $refresh) {
                    static::guarded($action, fn () => $flow()->approve($user(), $record, $data['remarks'] ?? null), 'Approval recorded');
                    $refresh($record);
                }),
            Action::make('certifyFunds')
                ->label('Certify funds and reserve')
                ->icon('heroicon-o-banknotes')->color('success')
                ->visible(fn (PurchaseRequest $record) => $canAct($record) && $atBudget($record))
                ->schema(fn (PurchaseRequest $record) => [
                    Select::make('allocation_id')->label('Allotment')->required()
                        ->options(BudgetAllocation::query()
                            ->with(['fundSource', 'accountCode'])
                            ->where('agency_id', $record->agency_id)->where('fiscal_year_id', $record->fiscal_year_id)
                            ->where('status', BudgetAllocation::STATUS_APPROVED)
                            ->where(fn (Builder $q) => $q->whereNull('department_id')->orWhere('department_id', $record->department_id))
                            ->get()
                            ->mapWithKeys(fn (BudgetAllocation $a) => [$a->id => "{$a->reference_no} — {$a->fundSource->name} / {$a->accountCode->code} — available "
                                .Money::format(Money::fromCents(app(BudgetLedger::class)->balance($a)->available()))])),
                    Textarea::make('remarks')->maxLength(2000),
                ])
                ->modalSubmitActionLabel('Certify and reserve')
                ->modalDescription(fn (PurchaseRequest $record) => 'Reserves '.Money::format((string) $record->total_amount).' against the chosen allotment.')
                ->action(function (PurchaseRequest $record, array $data, Action $action) use ($flow, $user, $refresh) {
                    static::guarded($action, fn () => $flow()->certifyFunds($user(), $record, BudgetAllocation::findOrFail($data['allocation_id']), $data['remarks'] ?? null), 'Funds certified and reserved');
                    $refresh($record);
                }),
            Action::make('return')
                ->label('Return for correction')
                ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible(fn (PurchaseRequest $record) => $canAct($record))
                ->schema([Textarea::make('remarks')->label('What needs to change')->required()->maxLength(2000)])
                ->action(function (PurchaseRequest $record, array $data, Action $action) use ($flow, $user, $refresh) {
                    static::guarded($action, fn () => $flow()->returnForCorrection($user(), $record, $data['remarks']), 'Returned to the requester');
                    $refresh($record);
                }),
            Action::make('reject')
                ->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn (PurchaseRequest $record) => $canAct($record))
                ->requiresConfirmation()
                ->schema([Textarea::make('remarks')->label('Reason')->required()->maxLength(2000)])
                ->action(function (PurchaseRequest $record, array $data, Action $action) use ($flow, $user, $refresh) {
                    static::guarded($action, fn () => $flow()->reject($user(), $record, $data['remarks']), 'Request rejected');
                    $refresh($record);
                }),
            Action::make('cancel')
                ->label('Cancel request')
                ->icon('heroicon-o-trash')->color('gray')
                ->visible(fn (PurchaseRequest $record) => in_array($record->status, [
                    PurchaseRequestStatus::Draft, PurchaseRequestStatus::DepartmentReview, PurchaseRequestStatus::BudgetReview,
                    PurchaseRequestStatus::Returned, PurchaseRequestStatus::Approved,
                ], true) && ($user()?->id === $record->requested_by || ($user()?->hasPermission('purchase_request.create', $record->department) ?? false)))
                ->requiresConfirmation()
                ->schema([Textarea::make('reason')->required()->maxLength(2000)])
                ->action(function (PurchaseRequest $record, array $data, Action $action) use ($flow, $user, $refresh) {
                    static::guarded($action, fn () => $flow()->cancel($user(), $record, $data['reason']), 'Request cancelled');
                    $refresh($record);
                }),
        ];
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseRequests::route('/'),
            'view' => ViewPurchaseRequest::route('/{record}'),
            'edit' => EditPurchaseRequest::route('/{record}/edit'),
        ];
    }
}
