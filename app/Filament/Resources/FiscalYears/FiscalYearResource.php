<?php

namespace App\Filament\Resources\FiscalYears;

use App\Filament\Resources\FiscalYears\Pages\ManageFiscalYears;
use App\Models\FiscalYear;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class FiscalYearResource extends Resource
{
    protected static ?string $model = FiscalYear::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('agency_id')->relationship('agency', 'name')->required()->disabledOn('edit'),
            TextInput::make('year')->required()->integer()->minValue(2000)->maxValue(2100)->disabledOn('edit')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('agency_id', $get('agency_id'))),
            DatePicker::make('starts_on')->required(),
            DatePicker::make('ends_on')->required()->after('starts_on'),
            Select::make('status')->required()->default(FiscalYear::STATUS_OPEN)->options([
                FiscalYear::STATUS_OPEN => 'Open',
                FiscalYear::STATUS_CLOSED => 'Closed (final)',
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('agency'))
            ->columns([
                TextColumn::make('agency.code')->label('Agency')->sortable(),
                TextColumn::make('year')->sortable(),
                TextColumn::make('starts_on')->date(),
                TextColumn::make('ends_on')->date(),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state) => $state === FiscalYear::STATUS_OPEN ? 'success' : 'gray'),
            ])
            ->defaultSort('year', 'desc')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFiscalYears::route('/')];
    }
}
