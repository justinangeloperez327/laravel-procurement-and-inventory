<?php

namespace App\Filament\Resources\ProcurementProjects\RelationManagers;

use App\Domain\Procurement\ProjectLots;
use App\Domain\Shared\Money;
use App\Enums\ProcurementProjectStatus;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Models\ProcurementProject;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LotsRelationManager extends RelationManager
{
    use ReportsDomainViolations;

    protected static string $relationship = 'lots';

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
                TextColumn::make('lot_no')->label('Lot'),
                TextColumn::make('title'),
                TextColumn::make('approved_budget')->label('ABC')->formatStateUsing(fn ($state) => Money::format((string) $state)),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('lot_no')
            ->headerActions([
                Action::make('newLot')
                    ->label('Create lot')
                    ->visible(fn () => $project->status === ProcurementProjectStatus::Proposed && (Filament::auth()->user()?->hasPermission('procurement.manage') ?? false))
                    ->schema([
                        TextInput::make('title')->required()->maxLength(255),
                        CheckboxList::make('lines')->required()
                            ->options(fn () => $project->items()->whereNull('procurement_lot_id')->with('purchaseRequestItem')->get()
                                ->mapWithKeys(fn ($i) => [$i->id => $i->purchaseRequestItem->description.' ('.Money::format((string) $i->approved_budget).')'])),
                    ])
                    ->action(fn (array $data, Action $action) => static::guarded($action,
                        fn () => app(ProjectLots::class)->create(Filament::auth()->user(), $project, $data['title'], array_map('intval', $data['lines'])), 'Lot created')),
            ]);
    }
}
