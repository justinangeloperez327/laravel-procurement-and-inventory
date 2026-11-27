<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Domain\Identity\GrantRole;
use App\Domain\Identity\RevokeRole;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Models\Department;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Role assignments are append-only: they are granted and revoked through domain actions,
 * never edited or deleted, so the grant history remains complete.
 */
class RoleAssignmentsRelationManager extends RelationManager
{
    use ReportsDomainViolations;

    protected static string $relationship = 'roleAssignments';

    protected static ?string $title = 'Roles';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['role', 'department', 'grantor', 'revoker']))
            ->columns([
                TextColumn::make('role.name')->label('Role'),
                TextColumn::make('department.name')->label('Scope')->placeholder('Agency-wide'),
                TextColumn::make('status')->badge()
                    ->state(fn (RoleAssignment $r) => match (true) {
                        $r->revoked_at !== null => 'Revoked',
                        $r->isActive() => 'Active',
                        default => 'Expired',
                    })
                    ->color(fn (string $state) => $state === 'Active' ? 'success' : 'gray'),
                TextColumn::make('granted_at')->dateTime()->sortable(),
                TextColumn::make('grantor.name')->label('Granted by'),
                TextColumn::make('expires_at')->dateTime()->placeholder('No expiry'),
                TextColumn::make('revoked_at')->dateTime()->placeholder('—'),
                TextColumn::make('revocation_reason')->limit(40)->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('current')->label('Current')->default(true)
                    ->queries(
                        true: fn (Builder $q) => $q->active(),
                        false: fn (Builder $q) => $q->where(fn (Builder $w) => $w->whereNotNull('revoked_at')->orWhere('expires_at', '<=', now())),
                    ),
            ])
            ->defaultSort('granted_at', 'desc')
            ->headerActions([$this->grantAction()])
            ->recordActions([$this->revokeAction()]);
    }

    private function canAdminister(): bool
    {
        $actor = Filament::auth()->user();

        return $actor instanceof User && $actor->is_admin && $actor->is_active && ! $actor->is($this->getOwnerRecord());
    }

    private function grantAction(): Action
    {
        return Action::make('grant')
            ->label('Grant role')
            ->visible(fn () => $this->canAdminister())
            ->schema([
                Select::make('role_id')->label('Role')->required()
                    ->options(fn () => Role::orderBy('name')->pluck('name', 'id')),
                Select::make('department_id')->label('Department scope')
                    ->helperText('Leave empty for agency-wide authority.')
                    ->options(fn () => Department::where('agency_id', $this->getOwnerRecord()->agency_id)
                        ->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                DateTimePicker::make('expires_at')->label('Expires')->after('now'),
                Textarea::make('reason')->label('Basis / authority')->required()->maxLength(2000)
                    ->helperText('Reference the office order or designation that authorizes this grant.'),
            ])
            ->action(function (array $data, Action $action) {
                abort_unless($this->canAdminister(), 403);
                static::guarded($action, fn () => app(GrantRole::class)(
                    Filament::auth()->user(),
                    $this->getOwnerRecord(),
                    Role::findOrFail($data['role_id']),
                    filled($data['department_id'] ?? null) ? Department::findOrFail($data['department_id']) : null,
                    filled($data['expires_at'] ?? null) ? CarbonImmutable::parse($data['expires_at']) : null,
                    $data['reason'],
                ), 'Role granted');
            });
    }

    private function revokeAction(): Action
    {
        return Action::make('revoke')
            ->label('Revoke')
            ->color('danger')
            ->icon('heroicon-o-no-symbol')
            ->visible(fn (RoleAssignment $record) => $this->canAdminister() && $record->revoked_at === null)
            ->requiresConfirmation()
            ->schema([Textarea::make('reason')->label('Reason for revocation')->required()->maxLength(2000)])
            ->action(function (RoleAssignment $record, array $data, Action $action) {
                abort_unless($this->canAdminister(), 403);
                static::guarded($action, fn () => app(RevokeRole::class)(Filament::auth()->user(), $record, $data['reason']), 'Role revoked');
            });
    }
}
