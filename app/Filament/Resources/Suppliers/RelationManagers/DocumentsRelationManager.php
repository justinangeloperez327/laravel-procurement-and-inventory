<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Domain\Documents\DocumentStore;
use App\Domain\Suppliers\SupplierDocuments;
use App\Domain\Suppliers\SupplierDocumentTypes;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Eligibility documents: uploaded by a registrar, verified by someone else, never edited once verified. */
class DocumentsRelationManager extends RelationManager
{
    use ReportsDomainViolations;

    protected static string $relationship = 'documents';

    protected static ?string $title = 'Eligibility documents';

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['recordedBy', 'verifiedBy', 'document.versions']))
            ->columns([
                TextColumn::make('document_type')->label('Document')->formatStateUsing(fn (string $state) => SupplierDocumentTypes::label($state))->wrap(),
                TextColumn::make('reference_no')->placeholder('—'),
                TextColumn::make('issued_on')->date()->placeholder('—'),
                TextColumn::make('expires_on')->date()->placeholder('No expiry')
                    ->color(fn (SupplierDocument $record) => $record->expires_on?->isPast() && ! $record->expires_on->isToday() ? 'danger' : null),
                TextColumn::make('recordedBy.name')->label('Recorded by'),
                TextColumn::make('verified_at')->label('Verified')->dateTime()->placeholder('Not verified')
                    ->description(fn (SupplierDocument $record) => $record->verifiedBy?->name),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                Action::make('add')
                    ->label('Add document')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn () => $user()?->can('update', $supplier) ?? false)
                    ->schema([
                        Select::make('document_type')->options(SupplierDocumentTypes::options())->required()->live(),
                        TextInput::make('reference_no')->maxLength(64),
                        DatePicker::make('issued_on'),
                        DatePicker::make('expires_on')
                            ->required(fn (Get $get) => SupplierDocumentTypes::TYPES[$get('document_type')]['expires'] ?? false),
                        FileUpload::make('file')->required()->storeFiles(false)
                            ->acceptedFileTypes(DocumentStore::ALLOWED_MIME_TYPES)->maxSize(DocumentStore::MAX_BYTES / 1024),
                    ])
                    ->action(fn (array $data, Action $action) => static::guarded($action, fn () => app(SupplierDocuments::class)->add(
                        $user(), $supplier, $data['document_type'], $data['file'], $data['reference_no'] ?? null,
                        $data['issued_on'] ?? null, $data['expires_on'] ?? null,
                    ), 'Document recorded')),
            ])
            ->recordActions([
                Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (SupplierDocument $record) => $record->document !== null && ($user()?->can('view', $record->document) ?? false))
                    ->url(fn (SupplierDocument $record) => app(DocumentStore::class)->temporaryUrl($record->document->versions->sortByDesc('version')->first()))
                    ->openUrlInNewTab(),
                Action::make('verify')
                    ->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (SupplierDocument $record) => $record->verified_at === null && $record->recorded_by !== $user()?->id
                        && ($user()?->hasPermission('supplier.verify') ?? false))
                    ->requiresConfirmation()
                    ->modalDescription('Confirm you checked the document against its original or the issuing office.')
                    ->action(fn (SupplierDocument $record, Action $action) => static::guarded(
                        $action, fn () => app(SupplierDocuments::class)->verify($user(), $record), 'Document verified')),
            ]);
    }
}
