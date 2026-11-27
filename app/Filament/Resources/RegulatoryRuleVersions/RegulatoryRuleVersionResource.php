<?php

namespace App\Filament\Resources\RegulatoryRuleVersions;

use App\Domain\Regulatory\ApproveRuleVersion;
use App\Domain\Regulatory\RetireRuleVersion;
use App\Enums\ProcurementCategory;
use App\Enums\RuleStatus;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Filament\Resources\RegulatoryRuleVersions\Pages\CreateRegulatoryRuleVersion;
use App\Filament\Resources\RegulatoryRuleVersions\Pages\EditRegulatoryRuleVersion;
use App\Filament\Resources\RegulatoryRuleVersions\Pages\ListRegulatoryRuleVersions;
use App\Filament\Resources\RegulatoryRuleVersions\Pages\ViewRegulatoryRuleVersion;
use App\Models\RegulatoryRuleVersion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\KeyValueEntry;
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
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Effective-dated rule interpretations. Drafts are edited here; only a legal reviewer other than the
 * preparer can approve one, after which it is frozen and becomes enforceable through RuleBook.
 */
class RegulatoryRuleVersionResource extends Resource
{
    use ReportsDomainViolations;

    protected static ?string $model = RegulatoryRuleVersion::class;

    protected static ?string $modelLabel = 'rule version';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Regulatory';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Rule')->columns(2)->columnSpanFull()->schema([
                Select::make('regulatory_source_id')->label('Source')->relationship('regulatorySource', 'reference')->required()->searchable()->preload(),
                TextInput::make('rule_code')->required()->maxLength(64)->placeholder('SVP-CEILING-GOODS'),
                TextInput::make('version')->integer()->required()->minValue(1)->default(1)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('rule_code', $get('rule_code'))),
                TextInput::make('provision')->required()->maxLength(255)->placeholder('Article IV, Section …'),
                TextInput::make('legal_reference')->maxLength(255)->helperText('Exact provision and link. Required for approval.'),
                TextInput::make('entity_type')->maxLength(64),
                Select::make('category')->options(collect(ProcurementCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                TextInput::make('test_reference')->maxLength(255)->helperText('Test proving the rule. Required for approval.'),
            ]),
            Section::make('Effectivity')->columns(2)->columnSpanFull()->schema([
                DatePicker::make('effective_from')->required(),
                DatePicker::make('effective_to')->after('effective_from')->helperText('Leave empty if open-ended.'),
            ]),
            Section::make('Interpretation')->columnSpanFull()->schema([
                KeyValue::make('conditions')->keyLabel('Condition')->valueLabel('Value'),
                KeyValue::make('exceptions')->keyLabel('Exception')->valueLabel('Basis'),
                Textarea::make('approved_interpretation')->rows(5)->helperText('The interpretation the legal reviewer signs off. Required for approval.'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('rule_code'),
            TextEntry::make('version'),
            TextEntry::make('status')->badge()->formatStateUsing(fn (RuleStatus $state) => $state->label()),
            TextEntry::make('regulatorySource.reference')->label('Source'),
            TextEntry::make('provision'),
            TextEntry::make('legal_reference')->placeholder('—'),
            TextEntry::make('effective_from')->date(),
            TextEntry::make('effective_to')->date()->placeholder('Open-ended'),
            TextEntry::make('preparedBy.name')->label('Prepared by')->placeholder('—'),
            TextEntry::make('reviewedBy.name')->label('Reviewed by')->placeholder('Not yet reviewed'),
            TextEntry::make('reviewed_at')->dateTime()->placeholder('—'),
            TextEntry::make('test_reference')->placeholder('—'),
            KeyValueEntry::make('conditions')->columnSpanFull(),
            KeyValueEntry::make('exceptions')->columnSpanFull(),
            TextEntry::make('approved_interpretation')->columnSpanFull()->placeholder('—'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['regulatorySource', 'reviewedBy']))
            ->columns([
                TextColumn::make('rule_code')->searchable()->sortable(),
                TextColumn::make('version')->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (RuleStatus $state) => $state->label())
                    ->color(fn (RuleStatus $state) => match ($state) {
                        RuleStatus::Approved => 'success',
                        RuleStatus::Draft => 'warning',
                        RuleStatus::Retired => 'gray',
                    }),
                TextColumn::make('regulatorySource.reference')->label('Source'),
                TextColumn::make('effective_from')->date()->sortable(),
                TextColumn::make('effective_to')->date()->placeholder('Open-ended'),
                TextColumn::make('reviewedBy.name')->label('Reviewer')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(RuleStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
            ])
            ->defaultSort('rule_code')
            ->recordActions([ViewAction::make(), EditAction::make(), ...static::reviewActions()]);
    }

    /** @return list<Action> */
    public static function reviewActions(): array
    {
        return [
            Action::make('approve')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->authorize(fn (RegulatoryRuleVersion $record) => Filament::auth()->user()?->can('approve', $record) ?? false)
                ->requiresConfirmation()
                ->modalDescription('Approval makes this interpretation enforceable from its effective date and freezes it.')
                ->action(fn (RegulatoryRuleVersion $record, Action $action) => static::guarded(
                    $action, fn () => app(ApproveRuleVersion::class)(Filament::auth()->user(), $record), 'Rule version approved',
                )),
            Action::make('retire')
                ->icon('heroicon-o-archive-box-x-mark')
                ->color('danger')
                ->authorize(fn (RegulatoryRuleVersion $record) => Filament::auth()->user()?->can('retire', $record) ?? false)
                ->requiresConfirmation()
                ->schema([Textarea::make('reason')->required()->maxLength(2000)])
                ->action(fn (RegulatoryRuleVersion $record, array $data, Action $action) => static::guarded(
                    $action, fn () => app(RetireRuleVersion::class)(Filament::auth()->user(), $record, $data['reason']), 'Rule version retired',
                )),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegulatoryRuleVersions::route('/'),
            'create' => CreateRegulatoryRuleVersion::route('/create'),
            'view' => ViewRegulatoryRuleVersion::route('/{record}'),
            'edit' => EditRegulatoryRuleVersion::route('/{record}/edit'),
        ];
    }
}
