<?php

namespace App\Filament\Resources\ProcurementProjects\RelationManagers;

use App\Domain\Bidding\CompetitiveBidding;
use App\Domain\Documents\DocumentStore;
use App\Enums\ProcurementMode;
use App\Enums\ProcurementProjectStatus;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Models\Bidding;
use App\Models\Meeting;
use App\Models\ProcurementProject;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BiddingsRelationManager extends RelationManager
{
    use ReportsDomainViolations;

    protected static string $relationship = 'biddings';

    protected static ?string $title = 'Bidding';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->procurementMode?->code === ProcurementMode::CompetitiveBidding;
    }

    public function table(Table $table): Table
    {
        /** @var ProcurementProject $project */
        $project = $this->getOwnerRecord();
        $user = fn () => Filament::auth()->user();
        $upload = fn (string $name, string $label) => FileUpload::make($name)->label($label)->storeFiles(false)
            ->acceptedFileTypes(DocumentStore::ALLOWED_MIME_TYPES)->maxSize(DocumentStore::MAX_BYTES / 1024);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('bids'))
            ->columns([
                TextColumn::make('reference_no')->label('Reference'),
                TextColumn::make('deadline')->label('Submission deadline')->state(fn (Bidding $r) => CompetitiveBidding::effectiveDeadline($r))->dateTime(),
                TextColumn::make('opening')->label('Opening')->state(fn (Bidding $r) => CompetitiveBidding::effectiveDeadline($r, 'bid_opening'))->dateTime(),
                TextColumn::make('bids_count')->label('Bids received'),
                TextColumn::make('status')->badge(),
            ])
            ->headerActions([
                Action::make('openBidding')
                    ->label('Open bidding')
                    ->visible(fn () => ($user()?->hasPermission('rfq.manage') ?? false) && $project->status === ProcurementProjectStatus::Solicitation)
                    ->schema([
                        DateTimePicker::make('deadline')->label('Bid submission deadline')->required(),
                        DateTimePicker::make('opening')->label('Bid opening')->required()->afterOrEqual('deadline'),
                        TextInput::make('fee')->label('Bidding documents fee (₱)')->numeric()->minValue(0),
                    ])
                    ->action(fn (array $data, Action $action) => static::guarded($action, fn () => app(CompetitiveBidding::class)->open(
                        $user(), $project, $data['deadline'], $data['opening'], filled($data['fee'] ?? null) ? (string) $data['fee'] : null), 'Bidding opened')),
            ])
            ->recordActions([
                Action::make('receiveBid')
                    ->label('Receive bid')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->visible(fn (Bidding $r) => ($user()?->hasPermission('rfq.manage') ?? false) && $r->status === 'open')
                    ->modalDescription('The receipt time is the server clock now. Envelopes are stored sealed and cannot be read until opening.')
                    ->schema([
                        Select::make('supplier_id')->label('Bidder')->required()->searchable()
                            ->options(Supplier::where('agency_id', $project->agency_id)->orderBy('name')->pluck('name', 'id')),
                        $upload('eligibility_technical', 'Eligibility and technical envelope'),
                        $upload('financial', 'Financial envelope'),
                    ])
                    ->action(fn (Bidding $r, array $data, Action $action) => static::guarded($action, fn () => app(CompetitiveBidding::class)->receive(
                        $user(), $r, Supplier::findOrFail($data['supplier_id']),
                        array_filter(['eligibility_technical' => $data['eligibility_technical'] ?? null, 'financial' => $data['financial'] ?? null])), 'Bid received')),
                Action::make('openBids')
                    ->label('Open bids')
                    ->icon('heroicon-o-lock-open')->color('warning')
                    ->visible(fn (Bidding $r) => ($user()?->hasPermission('bid.open') ?? false) && $r->status === 'open')
                    ->schema(fn (Bidding $r) => [
                        Select::make('meeting_id')->label('Opening session (held BAC meeting with quorum)')->required()
                            ->options(Meeting::where('procurement_project_id', $project->id)->where('quorum_met', true)->orderByDesc('held_at')->get()
                                ->mapWithKeys(fn (Meeting $m) => [$m->id => "{$m->meeting_type} — {$m->held_at}"])),
                        ...$r->bids()->with('supplier')->where('is_late', false)->whereNull('withdrawn_at')->get()
                            ->map(fn ($bid) => TextInput::make("amounts.{$bid->id}")->label("Amount read — {$bid->supplier->name} ({$bid->receipt_reference})")
                                ->numeric()->required()->gt(0))->all(),
                    ])
                    ->action(fn (Bidding $r, array $data, Action $action) => static::guarded($action, fn () => app(CompetitiveBidding::class)->openBids(
                        $user(), $r, Meeting::findOrFail($data['meeting_id']), array_map('strval', $data['amounts'] ?? [])), 'Bids opened')),
            ]);
    }
}
