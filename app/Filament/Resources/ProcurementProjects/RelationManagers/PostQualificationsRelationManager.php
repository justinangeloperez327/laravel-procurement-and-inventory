<?php

namespace App\Filament\Resources\ProcurementProjects\RelationManagers;

use App\Domain\Bidding\PostQualifications;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Models\PostQualification;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PostQualificationsRelationManager extends RelationManager
{
    use ReportsDomainViolations;

    protected static string $relationship = 'postQualifications';

    protected static ?string $title = 'Post-qualification';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['supplier', 'performedBy']))
            ->columns([
                TextColumn::make('supplier.name')->label('Supplier'),
                TextColumn::make('started_at')->dateTime(),
                TextColumn::make('result')->badge()->color(fn (string $state) => match ($state) {
                    'passed' => 'success', 'failed' => 'danger', default => 'warning',
                }),
                TextColumn::make('findings')->wrap()->placeholder('—'),
                TextColumn::make('performedBy.name')->label('By')->placeholder('System'),
            ])
            ->defaultSort('id')
            ->recordActions([
                Action::make('recordResult')
                    ->label('Record result')
                    ->visible(fn (PostQualification $r) => $r->result === 'pending' && (Filament::auth()->user()?->hasPermission('bid.evaluate') ?? false))
                    ->schema([
                        Select::make('result')->options(['passed' => 'Passed', 'failed' => 'Failed'])->required(),
                        Textarea::make('findings')->required()->maxLength(5000),
                    ])
                    ->action(fn (PostQualification $r, array $data, Action $action) => static::guarded($action, fn () => app(PostQualifications::class)->record(
                        Filament::auth()->user(), $r, $data['result'] === 'passed', $data['findings']), 'Result recorded')),
            ]);
    }
}
