<?php

namespace App\Filament\Resources\ProcurementProjects\RelationManagers;

use App\Domain\Bidding\Solicitations;
use App\Enums\ProcurementMode;
use App\Enums\ProcurementProjectStatus;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Models\ProcurementProject;
use App\Models\Rfq;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RfqsRelationManager extends RelationManager
{
    use ReportsDomainViolations;

    protected static string $relationship = 'rfqs';

    protected static ?string $title = 'RFQs';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->procurementMode !== null && $ownerRecord->procurementMode->code !== ProcurementMode::CompetitiveBidding;
    }

    public function table(Table $table): Table
    {
        /** @var ProcurementProject $project */
        $project = $this->getOwnerRecord();
        $canManage = fn () => Filament::auth()->user()?->hasPermission('rfq.manage') ?? false;

        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('quotations'))
            ->columns([
                TextColumn::make('reference_no')->label('Reference'),
                TextColumn::make('issued_at')->dateTime(),
                TextColumn::make('submission_deadline')->dateTime(),
                TextColumn::make('minimum_suppliers')->label('Min. quotations')->placeholder('—'),
                TextColumn::make('quotations_count')->label('Received'),
                TextColumn::make('status')->badge(),
            ])
            ->headerActions([
                Action::make('issueRfq')
                    ->label('Issue RFQ')
                    ->visible(fn () => $canManage() && $project->status === ProcurementProjectStatus::Solicitation)
                    ->schema([
                        DateTimePicker::make('deadline')->label('Submission deadline')->required(),
                        TextInput::make('minimum')->label('Minimum quotations sought')->integer()->minValue(1)->default(3),
                    ])
                    ->action(fn (array $data, Action $action) => static::guarded($action, fn () => app(Solicitations::class)->issueRfq(
                        Filament::auth()->user(), $project, $data['deadline'], (int) $data['minimum']), 'RFQ issued')),
            ])
            ->recordActions([
                Action::make('recordQuotation')
                    ->label('Record quotation')
                    ->visible(fn (Rfq $record) => $canManage() && $record->status === 'issued')
                    ->schema(fn (Rfq $record) => [
                        Select::make('supplier_id')->label('Supplier')->required()->searchable()
                            ->options(Supplier::where('agency_id', $project->agency_id)->where('status', 'active')->orderBy('name')->pluck('name', 'id')),
                        ...$project->items()->with('purchaseRequestItem')->get()->map(fn ($line) => TextInput::make("prices.{$line->id}")
                            ->label("Unit price — {$line->purchaseRequestItem->description} (qty {$line->quantity})")->numeric()->minValue(0)->required())->all(),
                        DatePicker::make('validity_until'),
                    ])
                    ->action(fn (Rfq $record, array $data, Action $action) => static::guarded($action, fn () => app(Solicitations::class)->recordQuotation(
                        Filament::auth()->user(), $record, Supplier::findOrFail($data['supplier_id']),
                        array_map('strval', $data['prices'] ?? []), $data['validity_until'] ?? null), 'Quotation recorded')),
                Action::make('close')
                    ->label('Close RFQ')
                    ->visible(fn (Rfq $record) => $canManage() && $record->status === 'issued')
                    ->requiresConfirmation()
                    ->action(fn (Rfq $record, Action $action) => static::guarded($action, fn () => app(Solicitations::class)->closeRfq(Filament::auth()->user(), $record), 'RFQ closed')),
            ]);
    }
}
