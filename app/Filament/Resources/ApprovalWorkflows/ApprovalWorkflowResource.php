<?php

namespace App\Filament\Resources\ApprovalWorkflows;

use App\Filament\Concerns\ScopedToUserAgency;
use App\Filament\Resources\ApprovalWorkflows\Pages\CreateApprovalWorkflow;
use App\Filament\Resources\ApprovalWorkflows\Pages\EditApprovalWorkflow;
use App\Filament\Resources\ApprovalWorkflows\Pages\ListApprovalWorkflows;
use App\Filament\Resources\ApprovalWorkflows\RelationManagers\StepsRelationManager;
use App\Models\ApprovalWorkflow;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Approval routes per record type. A workflow that has been used is frozen; publish a new one with a
 * later effective date to change the route.
 */
class ApprovalWorkflowResource extends Resource
{
    use ScopedToUserAgency;

    /** Record types that can be routed for approval (Approvable::approvalSubjectType()). */
    public const SUBJECT_TYPES = [
        'purchase_request' => 'Purchase request',
        'ppmp' => 'PPMP',
        'app_version' => 'APP version',
        'budget_allocation' => 'Budget allocation',
        'stock_requisition' => 'Stock requisition (RIS)',
    ];

    protected static ?string $model = ApprovalWorkflow::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            static::agencySelect(),
            TextInput::make('code')->required()->maxLength(64)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('agency_id', $get('agency_id'))),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('subject_type')->label('Applies to')->options(self::SUBJECT_TYPES)->required(),
            DatePicker::make('effective_from')->helperText('The newest active workflow already in effect is used.'),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('steps'))
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('subject_type')->label('Applies to')->formatStateUsing(fn (string $state) => self::SUBJECT_TYPES[$state] ?? $state),
                TextColumn::make('effective_from')->date()->placeholder('Always'),
                TextColumn::make('steps_count')->label('Steps'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('code')
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [StepsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalWorkflows::route('/'),
            'create' => CreateApprovalWorkflow::route('/create'),
            'edit' => EditApprovalWorkflow::route('/{record}/edit'),
        ];
    }
}
