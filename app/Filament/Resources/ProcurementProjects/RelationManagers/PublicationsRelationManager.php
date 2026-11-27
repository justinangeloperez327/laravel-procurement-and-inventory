<?php

namespace App\Filament\Resources\ProcurementProjects\RelationManagers;

use App\Domain\Procurement\ProjectSchedule;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Models\ProcurementProject;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PublicationsRelationManager extends RelationManager
{
    use ReportsDomainViolations;

    protected static string $relationship = 'publications';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var ProcurementProject $project */
        $project = $this->getOwnerRecord();

        return $table
            ->columns([
                TextColumn::make('channel')->formatStateUsing(fn (string $state) => ProjectSchedule::CHANNELS[$state] ?? $state),
                TextColumn::make('reference_no')->placeholder('—'),
                TextColumn::make('posted_at')->dateTime(),
                TextColumn::make('postedBy.name')->label('Recorded by'),
            ])
            ->headerActions([
                Action::make('recordPosting')
                    ->label('Record posting')
                    ->visible(fn () => Filament::auth()->user()?->hasPermission('procurement.manage') ?? false)
                    ->schema([
                        Select::make('channel')->options(ProjectSchedule::CHANNELS)->required(),
                        TextInput::make('reference_no')->label('Reference (e.g. PhilGEPS reference)')->maxLength(64),
                        DateTimePicker::make('posted_at')->required()->default(now()),
                    ])
                    ->action(fn (array $data, Action $action) => static::guarded($action, fn () => app(ProjectSchedule::class)->recordPublication(
                        Filament::auth()->user(), $project, $data['channel'], $data['posted_at'], $data['reference_no'] ?? null), 'Posting recorded')),
            ]);
    }
}
