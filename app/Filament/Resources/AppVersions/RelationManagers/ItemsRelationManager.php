<?php

namespace App\Filament\Resources\AppVersions\RelationManagers;

use App\Domain\Shared\Money;
use App\Enums\ProcurementMode;
use App\Models\AppItem;
use App\Models\AppVersion;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * APP items come only from consolidation; their budget is the sum of their PPMP sources. While the
 * version is a draft, the description, mode and schedule can be refined.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Procurement projects';

    public function isReadOnly(): bool
    {
        /** @var AppVersion $version */
        $version = $this->getOwnerRecord();

        return ! (auth()->user()?->can('update', $version) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('description')->required()->maxLength(255)->columnSpanFull(),
            Select::make('procurement_mode')->required()
                ->options(collect(ProcurementMode::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
            DatePicker::make('advertisement_on')->label('Advertisement / posting'),
            DatePicker::make('opening_on')->label('Submission / opening of bids'),
            DatePicker::make('award_on')->label('Notice of award'),
            DatePicker::make('contract_signing_on')->label('Contract signing'),
            Textarea::make('remarks')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('sources.ppmpItem.ppmp.department')->withCount('sources'))
            ->columns([
                TextColumn::make('code')->sortable(),
                TextColumn::make('description')->wrap(),
                TextColumn::make('procurement_mode')->label('Mode')->formatStateUsing(fn (ProcurementMode $state) => $state->label())->wrap(),
                TextColumn::make('estimated_budget')->label('Estimated budget')->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('sources')->label('From PPMPs')
                    ->state(fn (AppItem $record) => $record->sources->map(fn ($s) => $s->ppmpItem->ppmp->reference_no.' v'.$s->ppmpItem->ppmp->version.' ('.$s->ppmpItem->ppmp->department->name.')')->unique()->values()->all())
                    ->listWithLineBreaks()->limitList(3)->expandableLimitedList(),
                TextColumn::make('opening_on')->label('Opening')->date()->placeholder('—'),
            ])
            ->defaultSort('code')
            ->recordActions([EditAction::make()]);
    }
}
