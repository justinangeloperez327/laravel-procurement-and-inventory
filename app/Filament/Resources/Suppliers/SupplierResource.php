<?php

namespace App\Filament\Resources\Suppliers;

use App\Domain\Suppliers\ManageSupplierStatus;
use App\Domain\Suppliers\SupplierEligibility;
use App\Enums\SupplierStatus;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Filament\Concerns\ScopedToUserAgency;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Resources\Suppliers\Pages\ViewSupplier;
use App\Filament\Resources\Suppliers\RelationManagers\BeneficialOwnersRelationManager;
use App\Filament\Resources\Suppliers\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Suppliers\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Suppliers\RelationManagers\SanctionsRelationManager;
use App\Models\Supplier;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SupplierResource extends Resource
{
    use ReportsDomainViolations, ScopedToUserAgency;

    public const BUSINESS_TYPES = [
        'sole_proprietorship' => 'Sole proprietorship',
        'partnership' => 'Partnership',
        'corporation' => 'Corporation',
        'cooperative' => 'Cooperative',
        'joint_venture' => 'Joint venture',
    ];

    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Suppliers';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Registered name')->required()->maxLength(255),
            TextInput::make('trade_name')->maxLength(255),
            TextInput::make('tin')->label('TIN')->maxLength(32)
                ->disabled(fn (?Supplier $record) => $record !== null && $record->status !== SupplierStatus::Pending)
                ->helperText('Locked once the supplier is verified.'),
            TextInput::make('philgeps_registration_no')->label('PhilGEPS registration no.')->maxLength(64),
            Select::make('business_type')->options(self::BUSINESS_TYPES)->required(),
            TextInput::make('email')->email()->maxLength(255),
            TextInput::make('phone')->tel()->maxLength(32),
            Textarea::make('address')->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Eligibility today')->columnSpanFull()->schema([
                TextEntry::make('eligibility')->hiddenLabel()
                    ->state(function (Supplier $record) {
                        $result = app(SupplierEligibility::class)->check($record);

                        return $result->eligible() ? ['Eligible to participate'] : $result->reasons;
                    })
                    ->listWithLineBreaks()->bulleted()
                    ->color(fn (Supplier $record) => app(SupplierEligibility::class)->check($record)->eligible() ? 'success' : 'danger'),
            ]),
            Section::make('Registration')->columns(3)->columnSpanFull()->schema([
                TextEntry::make('name')->label('Registered name'),
                TextEntry::make('trade_name')->placeholder('—'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (SupplierStatus $state) => $state->label())
                    ->color(fn (SupplierStatus $state) => static::statusColor($state)),
                TextEntry::make('tin')->label('TIN')->placeholder('—'),
                TextEntry::make('philgeps_registration_no')->label('PhilGEPS no.')->placeholder('—'),
                TextEntry::make('business_type')->formatStateUsing(fn (string $state) => self::BUSINESS_TYPES[$state] ?? $state),
                TextEntry::make('email')->placeholder('—'),
                TextEntry::make('phone')->placeholder('—'),
                TextEntry::make('registered_at')->date()->placeholder('—'),
                TextEntry::make('address')->columnSpanFull()->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->wrap(),
                TextColumn::make('tin')->label('TIN')->searchable(),
                TextColumn::make('philgeps_registration_no')->label('PhilGEPS no.')->searchable()->toggleable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (SupplierStatus $state) => $state->label())
                    ->color(fn (SupplierStatus $state) => static::statusColor($state)),
                TextColumn::make('registered_at')->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(SupplierStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
            ])
            ->defaultSort('name')
            ->recordActions([ViewAction::make()]);
    }

    public static function statusColor(SupplierStatus $status): string
    {
        return match ($status) {
            SupplierStatus::Active => 'success',
            SupplierStatus::Pending => 'warning',
            SupplierStatus::Suspended, SupplierStatus::Blacklisted => 'danger',
            SupplierStatus::Inactive => 'gray',
        };
    }

    /** @return list<Action> */
    public static function statusActions(): array
    {
        $user = fn (): ?User => Filament::auth()->user();

        return [
            Action::make('activate')
                ->label('Verify and activate')
                ->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn (Supplier $record) => $record->status === SupplierStatus::Pending && ($user()?->hasPermission('supplier.verify') ?? false))
                ->requiresConfirmation()
                ->action(function (Supplier $record, Action $action) use ($user) {
                    static::guarded($action, fn () => app(ManageSupplierStatus::class)->activate($user(), $record), 'Supplier activated');
                    $record->refresh();
                }),
            Action::make('deactivate')
                ->icon('heroicon-o-pause-circle')->color('gray')
                ->visible(fn (Supplier $record) => $record->status !== SupplierStatus::Inactive && ($user()?->can('update', $record) ?? false))
                ->schema([Textarea::make('reason')->required()->maxLength(2000)])
                ->action(function (Supplier $record, array $data, Action $action) use ($user) {
                    static::guarded($action, fn () => app(ManageSupplierStatus::class)->deactivate($user(), $record, $data['reason']), 'Supplier deactivated');
                    $record->refresh();
                }),
            Action::make('reopen')
                ->label('Reopen for verification')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (Supplier $record) => $record->status === SupplierStatus::Inactive && ($user()?->can('update', $record) ?? false))
                ->schema([Textarea::make('reason')->required()->maxLength(2000)])
                ->action(function (Supplier $record, array $data, Action $action) use ($user) {
                    static::guarded($action, fn () => app(ManageSupplierStatus::class)->reopen($user(), $record, $data['reason']), 'Supplier reopened');
                    $record->refresh();
                }),
        ];
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
            BeneficialOwnersRelationManager::class,
            SanctionsRelationManager::class,
            ContactsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'view' => ViewSupplier::route('/{record}'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
