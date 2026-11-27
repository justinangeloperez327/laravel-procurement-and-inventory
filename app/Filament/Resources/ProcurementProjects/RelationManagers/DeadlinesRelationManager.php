<?php

namespace App\Filament\Resources\ProcurementProjects\RelationManagers;

use App\Domain\Procurement\ProjectSchedule;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Models\ProcurementDeadline;
use App\Models\ProcurementProject;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Deadlines are never edited: an extension adds a new row linked to the one it replaces. */
class DeadlinesRelationManager extends RelationManager
{
    use ReportsDomainViolations;

    protected static string $relationship = 'deadlines';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var ProcurementProject $project */
        $project = $this->getOwnerRecord();
        $canManage = fn () => Filament::auth()->user()?->hasPermission('procurement.manage') ?? false;
        $currentIds = fn () => ProjectSchedule::currentDeadlines($project)->pluck('id')->all();

        return $table
            ->columns([
                TextColumn::make('deadline_type')->label('Deadline')->formatStateUsing(fn (string $state) => ProjectSchedule::DEADLINE_TYPES[$state] ?? $state),
                TextColumn::make('due_at')->dateTime()->sortable(),
                TextColumn::make('current')->label('')->badge()
                    ->state(fn (ProcurementDeadline $r) => in_array($r->id, $currentIds(), true) ? 'Current' : 'Superseded')
                    ->color(fn (string $state) => $state === 'Current' ? 'success' : 'gray'),
                TextColumn::make('legal_basis')->placeholder('—'),
                TextColumn::make('extension_reason')->placeholder('—')->wrap(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                Action::make('setDeadline')
                    ->label('Set deadline')
                    ->visible($canManage)
                    ->schema([
                        Select::make('type')->options(ProjectSchedule::DEADLINE_TYPES)->required(),
                        DateTimePicker::make('due_at')->required(),
                        TextInput::make('legal_basis')->maxLength(255),
                    ])
                    ->action(fn (array $data, Action $action) => static::guarded($action, fn () => app(ProjectSchedule::class)->setDeadline(
                        Filament::auth()->user(), $project, $data['type'], $data['due_at'], $data['legal_basis'] ?? null), 'Deadline set')),
            ])
            ->recordActions([
                Action::make('extend')
                    ->icon('heroicon-o-clock')
                    ->visible(fn (ProcurementDeadline $r) => $canManage() && in_array($r->id, $currentIds(), true))
                    ->schema([
                        DateTimePicker::make('due_at')->label('New deadline')->required(),
                        Textarea::make('reason')->required()->maxLength(2000)->helperText('E.g. the bid bulletin announcing the extension.'),
                    ])
                    ->action(fn (ProcurementDeadline $r, array $data, Action $action) => static::guarded($action, fn () => app(ProjectSchedule::class)->extendDeadline(
                        Filament::auth()->user(), $r, $data['due_at'], $data['reason']), 'Deadline extended')),
            ]);
    }
}
