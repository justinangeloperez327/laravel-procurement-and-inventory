<?php

namespace App\Filament\Concerns;

use App\Domain\Approvals\ApprovalEngine;
use App\Domain\Identity\Authorizer;
use App\Domain\Planning\PlanApproval;
use App\Domain\Planning\PlanDocument;
use App\Enums\ApprovalDecision;
use App\Enums\PlanStatus;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;

/** Submit, approve and return actions for plan documents, backed by PlanApproval and the approval engine. */
trait PlanWorkflowActions
{
    use ReportsDomainViolations;

    /** @return list<Action> */
    public static function planWorkflowActions(): array
    {
        $user = fn (): ?User => Filament::auth()->user();

        return [
            Action::make('submit')
                ->label('Submit for approval')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn (PlanDocument&Model $record) => $record->isEditable() && $user() !== null
                    && app(Authorizer::class)->allows($user(), $record->submissionPermission(), $record->approvalDepartment()))
                ->requiresConfirmation()
                ->modalDescription('After submission the plan is locked until it is approved or returned.')
                ->action(fn (PlanDocument&Model $record, Action $action) => static::guarded(
                    $action, fn () => app(PlanApproval::class)->submit($user(), $record), 'Submitted for approval',
                )),
            Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (PlanDocument&Model $record) => $record->status === PlanStatus::Submitted && $user() !== null
                    && app(ApprovalEngine::class)->canAct($user(), $record))
                ->requiresConfirmation()
                ->schema([Textarea::make('remarks')->maxLength(2000)])
                ->action(fn (PlanDocument&Model $record, array $data, Action $action) => static::guarded(
                    $action, fn () => app(PlanApproval::class)->decide($user(), $record, ApprovalDecision::Approved, $data['remarks'] ?? null), 'Approval recorded',
                )),
            Action::make('return')
                ->label('Return for correction')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (PlanDocument&Model $record) => $record->status === PlanStatus::Submitted && $user() !== null
                    && app(ApprovalEngine::class)->canAct($user(), $record))
                ->schema([Textarea::make('remarks')->label('What needs to change')->required()->maxLength(2000)])
                ->action(fn (PlanDocument&Model $record, array $data, Action $action) => static::guarded(
                    $action, fn () => app(PlanApproval::class)->decide($user(), $record, ApprovalDecision::Returned, $data['remarks']), 'Returned to the preparer',
                )),
        ];
    }

    public static function planStatusColor(PlanStatus $status): string
    {
        return match ($status) {
            PlanStatus::Draft => 'gray',
            PlanStatus::Submitted => 'info',
            PlanStatus::Returned => 'warning',
            PlanStatus::Approved => 'success',
            PlanStatus::Superseded => 'gray',
        };
    }
}
