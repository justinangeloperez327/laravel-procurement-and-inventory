<?php

namespace App\Filament\Resources\ProcurementProjects\RelationManagers;

use App\Domain\Bidding\Disputes;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Models\ProcurementProject;
use App\Models\Protest;
use App\Models\ReconsiderationRequest;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Protests; requests for reconsideration are filed and decided from the header actions. */
class ProtestsRelationManager extends RelationManager
{
    use ReportsDomainViolations;

    protected static string $relationship = 'protests';

    protected static ?string $title = 'Disputes';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var ProcurementProject $project */
        $project = $this->getOwnerRecord();
        $user = fn () => Filament::auth()->user();
        $suppliers = fn () => Supplier::where('agency_id', $project->agency_id)->orderBy('name')->pluck('name', 'id');

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['supplier', 'decidedBy']))
            ->columns([
                TextColumn::make('filed_at')->dateTime(),
                TextColumn::make('supplier.name')->label('Protestant'),
                TextColumn::make('grounds')->limit(60)->wrap(),
                TextColumn::make('status')->badge(),
                IconColumn::make('blocks_award')->label('Blocks award')->boolean(),
                TextColumn::make('decision')->placeholder('—')->wrap(),
            ])
            ->headerActions([
                Action::make('fileReconsideration')
                    ->label('Request for reconsideration')
                    ->visible(fn () => $user()?->hasPermission('rfq.manage') ?? false)
                    ->schema([Select::make('supplier_id')->label('Supplier')->options($suppliers)->required(), Textarea::make('grounds')->required()])
                    ->action(fn (array $data, Action $action) => static::guarded($action, fn () => app(Disputes::class)->fileReconsideration(
                        $user(), $project, Supplier::findOrFail($data['supplier_id']), $data['grounds']), 'Request recorded')),
                Action::make('decideReconsideration')
                    ->label('Decide reconsideration')
                    ->visible(fn () => ($user()?->hasPermission('bac.recommend') ?? false) && $project->reconsiderationRequests()->where('status', 'filed')->exists())
                    ->schema([
                        Select::make('request_id')->label('Request')->required()
                            ->options(fn () => $project->reconsiderationRequests()->with('supplier')->where('status', 'filed')->get()->mapWithKeys(fn ($r) => [$r->id => "{$r->supplier->name} — {$r->filed_at}"])),
                        Select::make('decision')->options(['granted' => 'Grant', 'denied' => 'Deny'])->required(),
                        Textarea::make('text')->label('Decision')->required(),
                    ])
                    ->action(fn (array $data, Action $action) => static::guarded($action, fn () => app(Disputes::class)->decideReconsideration(
                        $user(), ReconsiderationRequest::findOrFail($data['request_id']), $data['decision'] === 'granted', $data['text']), 'Decision recorded')),
                Action::make('fileProtest')
                    ->label('File protest')
                    ->visible(fn () => $user()?->hasPermission('rfq.manage') ?? false)
                    ->schema([
                        Select::make('supplier_id')->label('Supplier')->options($suppliers)->required(),
                        Textarea::make('grounds')->required(),
                        TextInput::make('fee')->label('Protest fee (₱)')->numeric()->minValue(0),
                    ])
                    ->action(fn (array $data, Action $action) => static::guarded($action, fn () => app(Disputes::class)->fileProtest(
                        $user(), $project, Supplier::findOrFail($data['supplier_id']), $data['grounds'], filled($data['fee'] ?? null) ? (string) $data['fee'] : null), 'Protest filed')),
            ])
            ->recordActions([
                Action::make('decide')
                    ->label('HoPE decision')
                    ->visible(fn (Protest $r) => in_array($r->status, Disputes::OPEN, true) && ($user()?->hasPermission('award.approve') ?? false))
                    ->schema([Select::make('decision')->options(['granted' => 'Grant', 'denied' => 'Deny'])->required(), Textarea::make('text')->label('Decision')->required()])
                    ->action(fn (Protest $r, array $data, Action $action) => static::guarded($action, fn () => app(Disputes::class)->decideProtest(
                        $user(), $r, $data['decision'] === 'granted', $data['text']), 'Decision recorded')),
            ]);
    }
}
