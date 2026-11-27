<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Domain\Suppliers\BeneficialOwnership;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Models\Supplier;
use App\Models\SupplierBeneficialOwner;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BeneficialOwnersRelationManager extends RelationManager
{
    use ReportsDomainViolations;

    protected static string $relationship = 'beneficialOwners';

    protected static ?string $title = 'Beneficial owners';

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
                TextColumn::make('name'),
                TextColumn::make('nationality')->placeholder('—'),
                TextColumn::make('ownership_percentage')->label('Ownership')->formatStateUsing(fn ($state) => $state === null ? '—' : rtrim(rtrim($state, '0'), '.').'%'),
                TextColumn::make('control_basis')->placeholder('—'),
                TextColumn::make('declared_on')->date(),
                TextColumn::make('verified_at')->label('Verified')->dateTime()->placeholder('Not verified'),
            ])
            ->headerActions([
                Action::make('declare')
                    ->label('Add declaration')
                    ->visible(fn () => $user()?->can('update', $supplier) ?? false)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('nationality')->maxLength(64),
                        TextInput::make('ownership_percentage')->label('Ownership %')->numeric()->minValue(0.0001)->maxValue(100),
                        TextInput::make('control_basis')->maxLength(255)->helperText('Required if control is not by shareholding.'),
                    ])
                    ->action(fn (array $data, Action $action) => static::guarded($action, fn () => app(BeneficialOwnership::class)->declare(
                        $user(), $supplier, $data['name'], filled($data['ownership_percentage'] ?? null) ? (string) $data['ownership_percentage'] : null,
                        $data['nationality'] ?? null, $data['control_basis'] ?? null,
                    ), 'Declaration recorded')),
            ])
            ->recordActions([
                Action::make('verify')
                    ->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (SupplierBeneficialOwner $record) => $record->verified_at === null && ($user()?->hasPermission('supplier.verify') ?? false))
                    ->requiresConfirmation()
                    ->action(fn (SupplierBeneficialOwner $record, Action $action) => static::guarded(
                        $action, fn () => app(BeneficialOwnership::class)->verify($user(), $record), 'Declaration verified')),
            ]);
    }
}
