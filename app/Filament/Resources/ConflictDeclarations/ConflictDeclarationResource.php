<?php

namespace App\Filament\Resources\ConflictDeclarations;

use App\Domain\Compliance\ConflictDeclarations;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Filament\Resources\ConflictDeclarations\Pages\ListConflictDeclarations;
use App\Models\ConflictDeclaration;
use App\Models\ProcurementProject;
use App\Models\Supplier;
use App\Models\User;
use App\Policies\ConflictDeclarationPolicy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ConflictDeclarationResource extends Resource
{
    use ReportsDomainViolations;

    protected static ?string $model = ConflictDeclaration::class;

    protected static ?string $navigationLabel = 'Conflict of interest';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Compliance';

    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['user', 'procurementProject', 'supplier', 'reviewedBy']);
        $user = Filament::auth()->user();
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return ConflictDeclarationPolicy::seesAll($user)
            ? $query->whereHas('user', fn (Builder $q) => $q->where('agency_id', $user->agency_id))
            : $query->where('user_id', $user->id);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('declared_at')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('Declarant')->searchable(),
                TextColumn::make('procurementProject.reference_no')->label('Project')->placeholder('—'),
                TextColumn::make('supplier.name')->label('Supplier')->placeholder('—')->wrap(),
                IconColumn::make('has_conflict')->label('Conflict')->boolean()->trueColor('danger')->falseColor('success'),
                TextColumn::make('action_taken')->formatStateUsing(fn (?string $state) => $state ? ConflictDeclarations::ACTIONS[$state] : null)->placeholder('—')->wrap(),
                TextColumn::make('reviewedBy.name')->label('Reviewed by')->placeholder('—'),
            ])
            ->defaultSort('declared_at', 'desc')
            ->recordActions([
                Action::make('review')
                    ->authorize(fn (ConflictDeclaration $record) => Filament::auth()->user()?->can('review', $record) ?? false)
                    ->schema([Textarea::make('notes')->required()->maxLength(5000)])
                    ->action(fn (ConflictDeclaration $record, array $data, Action $action) => static::guarded(
                        $action, fn () => app(ConflictDeclarations::class)->review(Filament::auth()->user(), $record, $data['notes']), 'Review recorded')),
            ]);
    }

    public static function declareAction(): Action
    {
        $agencyId = fn () => Filament::auth()->user()?->agency_id;

        return Action::make('declare')
            ->label('Make a declaration')
            ->authorize(fn () => Filament::auth()->user()?->can('create', ConflictDeclaration::class) ?? false)
            ->schema([
                Select::make('procurement_project_id')->label('Procurement project')->searchable()
                    ->options(fn () => ProcurementProject::where('agency_id', $agencyId())->orderByDesc('id')->limit(200)->pluck('title', 'id')),
                Select::make('supplier_id')->label('Supplier')->searchable()
                    ->options(fn () => Supplier::where('agency_id', $agencyId())->orderBy('name')->pluck('name', 'id')),
                Toggle::make('has_conflict')->label('I have a conflict of interest')->live(),
                Textarea::make('nature')->label('Nature of the conflict')->visible(fn (Get $get) => (bool) $get('has_conflict'))
                    ->required(fn (Get $get) => (bool) $get('has_conflict'))->maxLength(5000),
                Select::make('action_taken')->options(ConflictDeclarations::ACTIONS)->visible(fn (Get $get) => (bool) $get('has_conflict'))
                    ->required(fn (Get $get) => (bool) $get('has_conflict')),
            ])
            ->action(fn (array $data, Action $action) => static::guarded($action, fn () => app(ConflictDeclarations::class)->declare(
                Filament::auth()->user(),
                filled($data['procurement_project_id'] ?? null) ? ProcurementProject::findOrFail($data['procurement_project_id']) : null,
                filled($data['supplier_id'] ?? null) ? Supplier::findOrFail($data['supplier_id']) : null,
                (bool) ($data['has_conflict'] ?? false),
                $data['nature'] ?? null,
                $data['action_taken'] ?? null,
            ), 'Declaration recorded'));
    }

    public static function getPages(): array
    {
        return ['index' => ListConflictDeclarations::route('/')];
    }
}
