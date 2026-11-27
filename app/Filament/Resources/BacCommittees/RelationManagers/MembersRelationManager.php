<?php

namespace App\Filament\Resources\BacCommittees\RelationManagers;

use App\Domain\Bac\BacComposition;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Models\BacCommittee;
use App\Models\BacMember;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    use ReportsDomainViolations;

    protected static string $relationship = 'members';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var BacCommittee $committee */
        $committee = $this->getOwnerRecord();
        $canConstitute = fn () => Filament::auth()->user()?->can('update', $committee) ?? false;

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('user.name')->label('Name'),
                TextColumn::make('member_role')->label('Role')->formatStateUsing(fn (string $state) => BacComposition::MEMBER_ROLES[$state]['label'] ?? $state),
                TextColumn::make('designation_reference')->label('Designation')->placeholder('—'),
                TextColumn::make('starts_on')->date(),
                TextColumn::make('ends_on')->date()->placeholder('Serving'),
            ])
            ->headerActions([
                Action::make('designate')
                    ->label('Designate member')
                    ->visible($canConstitute)
                    ->schema([
                        Select::make('user_id')->label('Person')->required()->searchable()
                            ->options(fn () => User::where('agency_id', $committee->agency_id)->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                        Select::make('member_role')->label('BAC role')->required()
                            ->options(collect(BacComposition::MEMBER_ROLES)->map(fn ($r) => $r['label'])),
                        TextInput::make('designation')->label('Designation reference')->maxLength(128),
                        DatePicker::make('starts_on')->required()->default(now()),
                        DatePicker::make('ends_on')->after('starts_on'),
                    ])
                    ->action(fn (array $data, Action $action) => static::guarded($action, fn () => app(BacComposition::class)->addMember(
                        Filament::auth()->user(), $committee, User::findOrFail($data['user_id']), $data['member_role'],
                        $data['designation'] ?? null, $data['starts_on'], $data['ends_on'] ?? null), 'Member designated')),
            ])
            ->recordActions([
                Action::make('end')
                    ->label('End designation')
                    ->visible(fn (BacMember $record) => $canConstitute() && $record->ends_on === null)
                    ->schema([DatePicker::make('ends_on')->required()->default(now()), Textarea::make('reason')->required()])
                    ->action(fn (BacMember $record, array $data, Action $action) => static::guarded($action,
                        fn () => app(BacComposition::class)->endMembership(Filament::auth()->user(), $record, $data['ends_on'], $data['reason']), 'Designation ended')),
            ]);
    }
}
