<?php

namespace App\Filament\Resources\Ppmps\RelationManagers;

use App\Domain\Shared\Money;
use App\Enums\PlanStatus;
use App\Enums\ProcurementMode;
use App\Models\Item;
use App\Models\MarketScopingItem;
use App\Models\Ppmp;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** PPMP lines; editable only while the PPMP is a draft or returned. Line budgets are computed by the database. */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function isReadOnly(): bool
    {
        /** @var Ppmp $ppmp */
        $ppmp = $this->getOwnerRecord();

        return ! (auth()->user()?->can('update', $ppmp) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        /** @var Ppmp $ppmp */
        $ppmp = $this->getOwnerRecord();

        return $schema->components([
            Select::make('item_id')->label('Catalog item')->searchable()->preload()
                ->relationship('item', 'name', fn (Builder $query) => $query->where('agency_id', $ppmp->agency_id)->where('is_active', true))
                ->live()
                ->afterStateUpdated(function (?int $state, Set $set) {
                    if ($item = Item::find($state)) {
                        $set('description', $item->name);
                        $set('unit_of_measure_id', $item->unit_of_measure_id);
                    }
                }),
            Select::make('market_scoping_item_id')->label('Market scoping basis')->searchable()
                ->options(fn () => MarketScopingItem::query()
                    ->whereHas('marketScoping', fn (Builder $q) => $q->where('department_id', $ppmp->department_id)
                        ->where('fiscal_year_id', $ppmp->fiscal_year_id)->where('status', PlanStatus::Approved))
                    ->pluck('description', 'id'))
                ->live()
                ->afterStateUpdated(fn (?int $state, Set $set) => ($m = MarketScopingItem::find($state)) ? $set('estimated_unit_cost', $m->estimated_unit_cost) : null),
            TextInput::make('description')->required()->maxLength(255)->columnSpanFull(),
            Select::make('unit_of_measure_id')->label('Unit')->relationship('unitOfMeasure', 'name')->required()->searchable()->preload(),
            TextInput::make('quantity')->numeric()->required()->gt(0),
            TextInput::make('estimated_unit_cost')->label('Unit cost (₱)')->numeric()->required()->minValue(0),
            Select::make('proposed_mode')->required()
                ->options(collect(ProcurementMode::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
            DatePicker::make('schedule_start'),
            DatePicker::make('schedule_end')->afterOrEqual('schedule_start'),
            Select::make('fund_source_id')->relationship('fundSource', 'name', fn (Builder $query) => $query->where('agency_id', $ppmp->agency_id)),
            Select::make('account_code_id')->relationship('accountCode', 'code')->searchable()->preload(),
            Textarea::make('remarks')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('unitOfMeasure'))
            ->columns([
                TextColumn::make('description')->wrap(),
                TextColumn::make('quantity')->numeric(),
                TextColumn::make('unitOfMeasure.code')->label('Unit'),
                TextColumn::make('estimated_unit_cost')->label('Unit cost')->formatStateUsing(fn ($state) => Money::formatUnitCost($state)),
                TextColumn::make('estimated_budget')->label('Estimated budget')->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('proposed_mode')->label('Mode')->formatStateUsing(fn (?ProcurementMode $state) => $state?->label())->wrap(),
                TextColumn::make('schedule_start')->date()->placeholder('—'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
