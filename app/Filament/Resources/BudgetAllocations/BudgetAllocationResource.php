<?php

namespace App\Filament\Resources\BudgetAllocations;

use App\Domain\Budget\AdjustBudget;
use App\Domain\Budget\ApproveBudgetAllocation;
use App\Domain\Budget\BudgetLedger;
use App\Domain\Shared\Money;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Filament\Concerns\ScopedToUserAgency;
use App\Filament\Resources\BudgetAllocations\Pages\CreateBudgetAllocation;
use App\Filament\Resources\BudgetAllocations\Pages\EditBudgetAllocation;
use App\Filament\Resources\BudgetAllocations\Pages\ListBudgetAllocations;
use App\Filament\Resources\BudgetAllocations\Pages\ViewBudgetAllocation;
use App\Filament\Resources\BudgetAllocations\RelationManagers\AdjustmentsRelationManager;
use App\Filament\Resources\BudgetAllocations\RelationManagers\ReservationsRelationManager;
use App\Models\BudgetAllocation;
use App\Models\FiscalYear;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

/** Budget allotments with their adjustment ledger and live balance (docs/09). */
class BudgetAllocationResource extends Resource
{
    use ReportsDomainViolations, ScopedToUserAgency;

    protected static ?string $model = BudgetAllocation::class;

    protected static ?string $modelLabel = 'allotment';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = 'Budget';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'reference_no';

    public static function form(Schema $schema): Schema
    {
        $agencyId = fn () => Filament::auth()->user()->agency_id;

        return $schema->components([
            Select::make('fiscal_year_id')->label('Fiscal year')->required()->disabledOn('edit')
                ->options(fn () => FiscalYear::where('agency_id', $agencyId())->where('status', FiscalYear::STATUS_OPEN)->orderByDesc('year')->pluck('year', 'id')),
            Select::make('department_id')->label('Department (optional)')
                ->relationship('department', 'name', fn (Builder $query) => $query->where('agency_id', $agencyId())),
            Select::make('fund_source_id')->required()
                ->relationship('fundSource', 'name', fn (Builder $query) => $query->where('agency_id', $agencyId())->where('is_active', true)),
            Select::make('account_code_id')->required()->searchable()->preload()
                ->relationship('accountCode', 'code', fn (Builder $query) => $query->where('is_active', true)),
            TextInput::make('amount')->label('Allotment (₱)')->numeric()->required()->gt(0),
            TextInput::make('authorization_reference')->required()->maxLength(128)->placeholder('GAA item, SARO or sub-allotment number'),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Allotment')->columns(3)->columnSpanFull()->schema([
                TextEntry::make('reference_no')->label('Reference'),
                TextEntry::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'approved' => 'success', 'closed' => 'gray', default => 'warning',
                }),
                TextEntry::make('fiscalYear.year')->label('Fiscal year'),
                TextEntry::make('fundSource.name')->label('Fund source'),
                TextEntry::make('accountCode.code')->label('Account code'),
                TextEntry::make('department.name')->label('Department')->placeholder('Agency-wide'),
                TextEntry::make('authorization_reference'),
                TextEntry::make('recordedBy.name')->label('Recorded by'),
                TextEntry::make('approvedBy.name')->label('Approved by')->placeholder('—'),
            ]),
            Section::make('Balance')->columns(4)->columnSpanFull()->schema(
                collect(['Allotted', 'Augmentations', 'Reductions', 'Realigned in', 'Realigned out', 'Adjusted allotment', 'Reserved', 'Obligated', 'Available', 'Disbursed', 'Unpaid obligations'])
                    ->map(fn (string $label) => TextEntry::make('balance_'.Str::snake($label))->label($label)
                        ->state(fn (BudgetAllocation $record) => app(BudgetLedger::class)->balance($record)->toDisplay()[$label])
                        ->weight(in_array($label, ['Available', 'Adjusted allotment'], true) ? 'bold' : null))
                    ->all()
            ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['fiscalYear', 'fundSource', 'accountCode', 'department']))
            ->columns([
                TextColumn::make('reference_no')->label('Reference')->searchable()->sortable(),
                TextColumn::make('fiscalYear.year')->label('FY'),
                TextColumn::make('fundSource.name')->label('Fund'),
                TextColumn::make('accountCode.code')->label('Account'),
                TextColumn::make('department.name')->placeholder('Agency-wide'),
                TextColumn::make('amount')->label('Allotted')->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('available')->label('Available')
                    ->state(fn (BudgetAllocation $record) => Money::format(Money::fromCents(app(BudgetLedger::class)->balance($record)->available()))),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'approved' => 'success', 'closed' => 'gray', default => 'warning',
                }),
            ])
            ->filters([
                SelectFilter::make('status')->options(['draft' => 'Draft', 'approved' => 'Approved', 'closed' => 'Closed']),
                SelectFilter::make('fiscal_year_id')->label('Fiscal year')->relationship('fiscalYear', 'year'),
            ])
            ->defaultSort('reference_no', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    /** @return list<Action> */
    public static function ledgerActions(): array
    {
        $user = fn () => Filament::auth()->user();
        $adjustmentFields = [
            TextInput::make('amount')->label('Amount (₱)')->numeric()->required()->gt(0),
            TextInput::make('authorization')->label('Authorization reference')->required()->maxLength(128),
            Textarea::make('reason')->required()->maxLength(2000),
        ];

        return [
            Action::make('approve')
                ->icon('heroicon-o-check-badge')->color('success')
                ->authorize(fn (BudgetAllocation $record) => $user()?->can('approve', $record) ?? false)
                ->requiresConfirmation()
                ->modalDescription('Confirm the allotment matches its authorization document. After approval the amount can change only through adjustments.')
                ->action(function (BudgetAllocation $record, Action $action) use ($user) {
                    static::guarded($action, fn () => app(ApproveBudgetAllocation::class)($user(), $record), 'Allotment approved');
                    $record->refresh();
                }),
            Action::make('augment')
                ->icon('heroicon-o-plus-circle')
                ->authorize(fn (BudgetAllocation $record) => $user()?->can('adjust', $record) ?? false)
                ->schema($adjustmentFields)
                ->action(fn (BudgetAllocation $record, array $data, Action $action) => static::guarded($action,
                    fn () => app(AdjustBudget::class)->augment($user(), $record, (string) $data['amount'], $data['authorization'], $data['reason'], (string) Str::uuid()),
                    'Augmentation recorded')),
            Action::make('reduce')
                ->icon('heroicon-o-minus-circle')->color('warning')
                ->authorize(fn (BudgetAllocation $record) => $user()?->can('adjust', $record) ?? false)
                ->schema($adjustmentFields)
                ->action(fn (BudgetAllocation $record, array $data, Action $action) => static::guarded($action,
                    fn () => app(AdjustBudget::class)->reduce($user(), $record, (string) $data['amount'], $data['authorization'], $data['reason'], (string) Str::uuid()),
                    'Reduction recorded')),
            Action::make('realign')
                ->label('Realign to…')
                ->icon('heroicon-o-arrows-right-left')
                ->authorize(fn (BudgetAllocation $record) => $user()?->can('adjust', $record) ?? false)
                ->schema(fn (BudgetAllocation $record) => [
                    Select::make('to_id')->label('Receiving allotment')->required()
                        ->options(BudgetAllocation::where('agency_id', $record->agency_id)->where('fiscal_year_id', $record->fiscal_year_id)
                            ->where('status', BudgetAllocation::STATUS_APPROVED)->whereKeyNot($record->id)->pluck('reference_no', 'id')),
                    ...$adjustmentFields,
                ])
                ->action(fn (BudgetAllocation $record, array $data, Action $action) => static::guarded($action,
                    fn () => app(AdjustBudget::class)->realign($user(), $record, BudgetAllocation::findOrFail($data['to_id']), (string) $data['amount'], $data['authorization'], $data['reason'], (string) Str::uuid()),
                    'Realignment recorded')),
        ];
    }

    public static function getRelations(): array
    {
        return [ReservationsRelationManager::class, AdjustmentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBudgetAllocations::route('/'),
            'create' => CreateBudgetAllocation::route('/create'),
            'view' => ViewBudgetAllocation::route('/{record}'),
            'edit' => EditBudgetAllocation::route('/{record}/edit'),
        ];
    }
}
