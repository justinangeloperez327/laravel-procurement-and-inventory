<?php

namespace App\Filament\Resources\ProcurementProjects\RelationManagers;

use App\Domain\Bidding\EvaluationCriteria;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Models\ProcurementProject;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CriteriaRelationManager extends RelationManager
{
    use ReportsDomainViolations;

    protected static string $relationship = 'criteria';

    protected static ?string $title = 'Evaluation criteria';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var ProcurementProject $project */
        $project = $this->getOwnerRecord();
        $canManage = fn () => Filament::auth()->user()?->hasPermission('rfq.manage') ?? false;

        return $table
            ->columns([
                TextColumn::make('sequence')->label('#'),
                TextColumn::make('criterion')->wrap(),
                TextColumn::make('criterion_type')->label('Type')->formatStateUsing(fn (string $state) => EvaluationCriteria::TYPES[$state] ?? $state),
                TextColumn::make('weight')->placeholder('—'),
                TextColumn::make('locked_at')->label('Published')->dateTime()->placeholder('Draft'),
            ])
            ->defaultSort('sequence')
            ->headerActions([
                Action::make('addCriterion')
                    ->label('Add criterion')
                    ->visible($canManage)
                    ->schema([
                        TextInput::make('criterion')->required()->maxLength(255),
                        Select::make('type')->options(EvaluationCriteria::TYPES)->required()->live(),
                        TextInput::make('weight')->numeric()->gt(0)->visible(fn (Get $get) => $get('type') === 'rated')->required(fn (Get $get) => $get('type') === 'rated'),
                        Textarea::make('description'),
                    ])
                    ->action(fn (array $data, Action $action) => static::guarded($action, fn () => app(EvaluationCriteria::class)->add(
                        Filament::auth()->user(), $project, $data['criterion'], $data['type'], isset($data['weight']) ? (string) $data['weight'] : null, null, $data['description'] ?? null), 'Criterion added')),
                Action::make('publish')
                    ->label('Publish and lock')
                    ->color('warning')
                    ->visible(fn () => $canManage() && $project->criteria()->whereNull('locked_at')->exists())
                    ->requiresConfirmation()
                    ->modalDescription('Published criteria are locked for good: they cannot be changed or removed, and none can be added once solicitation starts.')
                    ->action(fn (Action $action) => static::guarded($action, fn () => app(EvaluationCriteria::class)->publish(Filament::auth()->user(), $project), 'Criteria published')),
            ]);
    }
}
