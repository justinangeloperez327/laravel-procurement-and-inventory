<?php

namespace App\Filament\Resources\ProcurementProjects\RelationManagers;

use App\Domain\Compliance\ConflictDeclarations;
use App\Domain\Procurement\ModeSelection;
use App\Enums\ApprovalDecision;
use App\Enums\ProcurementMode;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Models\BacResolution;
use App\Models\ProcurementJustification;
use App\Models\ProcurementProject;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JustificationsRelationManager extends RelationManager
{
    use ReportsDomainViolations;

    protected static string $relationship = 'justifications';

    protected static ?string $title = 'Mode proposals';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var ProcurementProject $project */
        $project = $this->getOwnerRecord();
        $user = fn (): ?User => Filament::auth()->user();
        $independent = fn (ProcurementJustification $r) => $user() !== null && $user()->id !== $r->prepared_by
            && ConflictDeclarations::hasDeclared($user(), $project) && ! ConflictDeclarations::mustRecuse($user(), $project);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['procurementMode', 'preparedBy', 'reviewedBy', 'approvedBy', 'bacResolution']))
            ->columns([
                TextColumn::make('procurementMode.name')->label('Mode')->wrap(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'approved' => 'success', 'endorsed' => 'info', 'proposed' => 'warning', default => 'danger',
                }),
                TextColumn::make('screening')->label('Screening')
                    ->state(fn (ProcurementJustification $r) => array_map(
                        fn ($c) => ($c['passed'] ? '✓ ' : ($c['blocking'] ? '✗ ' : '! ')).$c['message'],
                        $r->screening_result['checks'] ?? [],
                    ) ?: ['No rules (default method)'])
                    ->listWithLineBreaks()->wrap(),
                TextColumn::make('justification')->limit(60)->wrap(),
                TextColumn::make('preparedBy.name')->label('Proposed by'),
                TextColumn::make('reviewedBy.name')->label('BAC review')->placeholder('—')
                    ->description(fn (ProcurementJustification $r) => $r->bacResolution?->resolution_no),
                TextColumn::make('approvedBy.name')->label('Final approval')->placeholder('—'),
                TextColumn::make('decision_remarks')->label('Remarks')->placeholder('—')->wrap(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('bacReview')
                    ->label('BAC review')
                    ->icon('heroicon-o-user-group')
                    ->visible(fn (ProcurementJustification $r) => $r->status === 'proposed' && ($user()?->hasPermission('procurement_mode.review') ?? false) && $independent($r))
                    ->schema(fn (ProcurementJustification $r) => [
                        Select::make('decision')->required()->live()->options([
                            ApprovalDecision::Endorsed->value => 'Endorse',
                            ApprovalDecision::Returned->value => 'Return for re-evaluation',
                            ApprovalDecision::Rejected->value => 'Reject this mode',
                        ]),
                        Select::make('resolution_id')->label('BAC resolution recommending the mode')
                            ->visible(fn (Get $get) => $get('decision') === ApprovalDecision::Endorsed->value && $r->procurementMode->code !== ProcurementMode::CompetitiveBidding)
                            ->required(fn (Get $get) => $get('decision') === ApprovalDecision::Endorsed->value && $r->procurementMode->code !== ProcurementMode::CompetitiveBidding)
                            ->options(BacResolution::where('procurement_project_id', $project->id)->where('resolution_type', 'recommend_mode')
                                ->get()->mapWithKeys(fn (BacResolution $res) => [$res->id => "{$res->resolution_no} — {$res->title}"])),
                        Textarea::make('remarks')->maxLength(2000)
                            ->required(fn (Get $get) => in_array($get('decision'), [ApprovalDecision::Returned->value, ApprovalDecision::Rejected->value], true)),
                    ])
                    ->action(fn (ProcurementJustification $r, array $data, Action $action) => static::guarded($action, fn () => app(ModeSelection::class)->bacReview(
                        $user(), $r, ApprovalDecision::from($data['decision']), $data['remarks'] ?? null,
                        filled($data['resolution_id'] ?? null) ? BacResolution::find($data['resolution_id']) : null,
                    ), 'BAC review recorded')),
                Action::make('hopeDecision')
                    ->label('HoPE decision')
                    ->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (ProcurementJustification $r) => $r->status === 'endorsed' && ($user()?->hasPermission('procurement_mode.approve') ?? false)
                        && $independent($r) && $user()->id !== $r->reviewed_by)
                    ->schema([
                        Select::make('decision')->required()->live()->options([
                            ApprovalDecision::Approved->value => 'Approve the mode',
                            ApprovalDecision::Returned->value => 'Return to the BAC',
                            ApprovalDecision::Rejected->value => 'Reject this mode',
                        ]),
                        Textarea::make('remarks')->maxLength(2000)
                            ->required(fn (Get $get) => in_array($get('decision'), [ApprovalDecision::Returned->value, ApprovalDecision::Rejected->value], true)),
                    ])
                    ->action(fn (ProcurementJustification $r, array $data, Action $action) => static::guarded($action, fn () => app(ModeSelection::class)->hopeDecision(
                        $user(), $r, ApprovalDecision::from($data['decision']), $data['remarks'] ?? null,
                    ), 'Decision recorded')),
            ]);
    }
}
