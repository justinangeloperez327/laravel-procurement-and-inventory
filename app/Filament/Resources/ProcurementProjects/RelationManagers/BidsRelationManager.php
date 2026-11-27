<?php

namespace App\Filament\Resources\ProcurementProjects\RelationManagers;

use App\Domain\Bidding\CompetitiveBidding;
use App\Domain\Shared\Money;
use App\Enums\ProcurementMode;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Models\Bid;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BidsRelationManager extends RelationManager
{
    use ReportsDomainViolations;

    protected static string $relationship = 'bids';

    protected static ?string $title = 'Bids received';

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
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['supplier', 'receivedBy']))
            ->columns([
                TextColumn::make('receipt_reference')->label('Receipt'),
                TextColumn::make('supplier.name')->label('Bidder'),
                TextColumn::make('submitted_at')->label('Received')->dateTime('M j, Y H:i:s'),
                TextColumn::make('bid_amount')->label('Amount')
                    ->state(fn (Bid $r) => $r->opened_at ? Money::format((string) $r->bid_amount) : ($r->is_late ? 'Late — returned unopened' : 'Sealed')),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'passed' => 'success', 'failed', 'returned_unopened', 'disqualified' => 'danger', 'withdrawn' => 'gray', default => 'info',
                }),
                TextColumn::make('receivedBy.name')->label('Received by'),
            ])
            ->defaultSort('submitted_at')
            ->recordActions([
                Action::make('withdraw')
                    ->visible(fn (Bid $r) => $r->opened_at === null && $r->withdrawn_at === null && ! $r->is_late
                        && (Filament::auth()->user()?->hasPermission('rfq.manage') ?? false))
                    ->requiresConfirmation()
                    ->schema([Textarea::make('reason')->required()])
                    ->action(fn (Bid $r, array $data, Action $action) => static::guarded($action,
                        fn () => app(CompetitiveBidding::class)->withdraw(Filament::auth()->user(), $r, $data['reason']), 'Bid withdrawn')),
            ]);
    }
}
