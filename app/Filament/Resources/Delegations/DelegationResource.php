<?php

namespace App\Filament\Resources\Delegations;

use App\Domain\Identity\RevokeDelegation;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Filament\Resources\Delegations\Pages\ManageDelegations;
use App\Models\Delegation;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DelegationResource extends Resource
{
    use ReportsDomainViolations;

    protected static ?string $model = Delegation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Access control';

    protected static ?int $navigationSort = 30;

    /** Users see delegations they gave or received; administrators and auditors see all. */
    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();
        $query = parent::getEloquentQuery()->with(['delegator', 'delegate', 'permission', 'department']);

        if ($user instanceof User && (($user->is_admin && $user->is_active) || $user->hasPermission('audit.read'))) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('delegator_id', $user?->getKey())
            ->orWhere('delegate_id', $user?->getKey()));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('permission.code')->label('Permission')->badge(),
                TextColumn::make('delegator.name')->label('From')->searchable(),
                TextColumn::make('delegate.name')->label('To')->searchable(),
                TextColumn::make('department.name')->label('Scope')->placeholder('Agency-wide'),
                TextColumn::make('starts_at')->dateTime()->sortable(),
                TextColumn::make('ends_at')->dateTime()->sortable(),
                TextColumn::make('status')->badge()
                    ->state(fn (Delegation $d) => match (true) {
                        $d->revoked_at !== null => 'Revoked',
                        $d->isActive() => 'Active',
                        $d->starts_at->isFuture() => 'Scheduled',
                        default => 'Expired',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Active' => 'success',
                        'Scheduled' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('reason')->limit(40),
            ])
            ->defaultSort('starts_at', 'desc')
            ->recordActions([
                Action::make('revoke')
                    ->color('danger')
                    ->icon('heroicon-o-no-symbol')
                    ->authorize(fn (Delegation $record) => Filament::auth()->user()?->can('revoke', $record) ?? false)
                    ->requiresConfirmation()
                    ->schema([Textarea::make('reason')->label('Reason for revocation')->required()->maxLength(2000)])
                    ->action(fn (Delegation $record, array $data, Action $action) => static::guarded(
                        $action,
                        fn () => app(RevokeDelegation::class)(Filament::auth()->user(), $record, $data['reason']),
                        'Delegation revoked',
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDelegations::route('/')];
    }
}
