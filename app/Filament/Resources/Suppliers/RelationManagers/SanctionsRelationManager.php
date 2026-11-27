<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Domain\Suppliers\SupplierSanctions;
use App\Enums\SanctionType;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Models\Supplier;
use App\Models\SupplierSanction;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SanctionsRelationManager extends RelationManager
{
    use ReportsDomainViolations;

    protected static string $relationship = 'sanctions';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var Supplier $supplier */
        $supplier = $this->getOwnerRecord();
        $user = fn () => Filament::auth()->user();

        return $table
            ->columns([
                TextColumn::make('sanction_type')->label('Type')->badge()->color('danger')->formatStateUsing(fn (SanctionType $state) => $state->label()),
                TextColumn::make('order_reference')->label('Order'),
                TextColumn::make('issuing_authority')->label('Issued by'),
                TextColumn::make('effective_from')->date(),
                TextColumn::make('effective_to')->date()->placeholder('Indefinite'),
                TextColumn::make('reason')->limit(50)->wrap(),
                TextColumn::make('lifted_at')->label('Lifted')->dateTime()->placeholder('—'),
            ])
            ->defaultSort('effective_from', 'desc')
            ->headerActions([
                Action::make('record')
                    ->label('Record sanction')
                    ->color('danger')
                    ->visible(fn () => $user()?->hasPermission('supplier.sanction') ?? false)
                    ->schema([
                        Select::make('sanction_type')->required()->options(collect(SanctionType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                        TextInput::make('issuing_authority')->required()->maxLength(255)->placeholder('GPPB, HoPE, court'),
                        TextInput::make('order_reference')->required()->maxLength(128),
                        DatePicker::make('effective_from')->required()->default(now()),
                        DatePicker::make('effective_to')->after('effective_from'),
                        Textarea::make('reason')->required()->maxLength(5000),
                    ])
                    ->action(fn (array $data, Action $action) => static::guarded($action, fn () => app(SupplierSanctions::class)->record(
                        $user(), $supplier, SanctionType::from($data['sanction_type']), $data['issuing_authority'], $data['order_reference'],
                        $data['reason'], $data['effective_from'], $data['effective_to'] ?? null,
                    ), 'Sanction recorded')),
            ])
            ->recordActions([
                Action::make('lift')
                    ->icon('heroicon-o-lock-open')
                    ->visible(fn (SupplierSanction $record) => $record->lifted_at === null && ($user()?->hasPermission('supplier.sanction') ?? false))
                    ->schema([Textarea::make('reason')->label('Lifting order / reason')->required()->maxLength(2000)])
                    ->action(fn (SupplierSanction $record, array $data, Action $action) => static::guarded(
                        $action, fn () => app(SupplierSanctions::class)->lift($user(), $record, $data['reason']), 'Sanction lifted')),
            ]);
    }
}
