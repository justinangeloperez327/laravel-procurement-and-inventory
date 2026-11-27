<?php

namespace App\Filament\Resources\Delegations\Pages;

use App\Domain\Identity\DelegatePermission;
use App\Domain\Identity\IdentityViolation;
use App\Filament\Resources\Delegations\DelegationResource;
use App\Models\Delegation;
use App\Models\Department;
use App\Models\Permission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageDelegations extends ManageRecords
{
    protected static string $resource = DelegationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delegate')
                ->label('Delegate my authority')
                ->authorize(fn () => Filament::auth()->user()?->can('create', Delegation::class) ?? false)
                ->schema([
                    Select::make('permission_id')->label('Permission')->required()
                        ->options(fn () => $this->heldPermissions())
                        ->helperText('Only authority you hold directly through a role can be delegated.'),
                    Select::make('delegate_id')->label('Delegate')->required()->searchable()
                        ->options(fn () => User::where('agency_id', $this->actor()->agency_id)
                            ->where('is_active', true)->whereKeyNot($this->actor()->id)
                            ->orderBy('name')->pluck('name', 'id')),
                    Select::make('department_id')->label('Department scope')
                        ->helperText('Leave empty to delegate agency-wide authority.')
                        ->options(fn () => Department::where('agency_id', $this->actor()->agency_id)
                            ->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                    DateTimePicker::make('starts_at')->required()->default(now()),
                    DateTimePicker::make('ends_at')->required()->after('starts_at'),
                    Textarea::make('reason')->required()->maxLength(2000)
                        ->helperText('E.g. official travel or leave, with the covering office order.'),
                ])
                ->action(function (array $data, Action $action) {
                    try {
                        app(DelegatePermission::class)(
                            $this->actor(),
                            User::findOrFail($data['delegate_id']),
                            Permission::findOrFail($data['permission_id']),
                            CarbonImmutable::parse($data['starts_at']),
                            CarbonImmutable::parse($data['ends_at']),
                            $data['reason'],
                            filled($data['department_id'] ?? null) ? Department::findOrFail($data['department_id']) : null,
                        );
                    } catch (IdentityViolation $e) {
                        Notification::make()->danger()->title('Delegation not allowed')->body($e->getMessage())->send();
                        $action->halt();

                        return;
                    }
                    Notification::make()->success()->title('Authority delegated')->send();
                }),
        ];
    }

    private function actor(): User
    {
        return Filament::auth()->user();
    }

    /** @return array<int, string> */
    private function heldPermissions(): array
    {
        return Permission::query()
            ->whereHas('roles.assignments', fn ($q) => $q->active()->where('user_id', $this->actor()->id))
            ->orderBy('code')
            ->pluck('code', 'id')
            ->all();
    }
}
