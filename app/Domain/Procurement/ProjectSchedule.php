<?php

namespace App\Domain\Procurement;

use App\Domain\Shared\TransitionStatus;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\ProcurementProjectStatus;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\ProcurementDeadline;
use App\Models\ProcurementEvent;
use App\Models\ProcurementProject;
use App\Models\ProcurementPublication;
use App\Models\RegulatoryRuleVersion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Deadlines, events and publications of a procurement project, and cancellation. */
class ProjectSchedule
{
    public const DEADLINE_TYPES = [
        'bid_submission' => 'Submission of bids / quotations',
        'bid_opening' => 'Opening of bids',
        'clarification' => 'Request for clarification',
        'pre_bid_conference' => 'Pre-bid conference',
        'post_qualification' => 'Post-qualification',
        'other' => 'Other',
    ];

    public const EVENT_TYPES = [
        'pre_procurement_conference' => 'Pre-procurement conference',
        'pre_bid_conference' => 'Pre-bid conference',
        'bid_opening' => 'Bid opening',
        'evaluation' => 'Evaluation',
        'post_qualification' => 'Post-qualification',
        'other' => 'Other',
    ];

    public const CHANNELS = [
        'philgeps' => 'PhilGEPS',
        'agency_website' => 'Agency website',
        'conspicuous_place' => 'Conspicuous place',
        'newspaper' => 'Newspaper',
    ];

    public function __construct(private TransitionStatus $transition) {}

    public function setDeadline(User $actor, ProcurementProject $project, string $type, string $dueAt, ?string $legalBasis = null, ?RegulatoryRuleVersion $rule = null): ProcurementDeadline
    {
        ProcurementGuard::require($actor, 'procurement.manage', $project);
        $this->assertOpen($project);
        if (! array_key_exists($type, self::DEADLINE_TYPES)) {
            throw new WorkflowViolation("Unknown deadline type {$type}.");
        }
        $current = self::currentDeadlines($project)->firstWhere('deadline_type', $type);
        if ($current !== null) {
            throw new WorkflowViolation('This deadline is already set. Extend it instead; deadlines are never overwritten.');
        }

        return ProcurementDeadline::create([
            'procurement_project_id' => $project->id, 'deadline_type' => $type, 'due_at' => $dueAt,
            'legal_basis' => $legalBasis, 'regulatory_rule_version_id' => $rule?->id, 'created_by' => $actor->id,
        ]);
    }

    /** Extending creates a new deadline linked to the original (docs/15 phase 6). */
    public function extendDeadline(User $actor, ProcurementDeadline $deadline, string $newDueAt, string $reason): ProcurementDeadline
    {
        $project = ProcurementProject::findOrFail($deadline->procurement_project_id);
        ProcurementGuard::require($actor, 'procurement.manage', $project);
        $this->assertOpen($project);
        if (blank($reason)) {
            throw new WorkflowViolation('A reason for the extension is required.');
        }

        return DB::transaction(function () use ($actor, $deadline, $newDueAt, $reason) {
            $locked = ProcurementDeadline::whereKey($deadline->id)->lockForUpdate()->firstOrFail();
            if (ProcurementDeadline::where('extended_from_id', $locked->id)->exists()) {
                throw new WorkflowViolation('This deadline was already extended; extend the latest one.');
            }
            if (Carbon::parse($newDueAt)->lte($locked->due_at)) {
                throw new WorkflowViolation('An extension must move the deadline later.');
            }

            $extended = ProcurementDeadline::create([
                'procurement_project_id' => $locked->procurement_project_id, 'deadline_type' => $locked->deadline_type,
                'due_at' => $newDueAt, 'legal_basis' => $locked->legal_basis, 'regulatory_rule_version_id' => $locked->regulatory_rule_version_id,
                'extended_from_id' => $locked->id, 'extension_reason' => $reason, 'created_by' => $actor->id,
            ]);
            AuditLog::record('procurement.deadline_extended', $extended, ['due_at' => $locked->due_at->toIso8601String()], ['due_at' => $extended->due_at->toIso8601String()], ['reason' => $reason]);

            return $extended;
        });
    }

    /** @return Collection<int, ProcurementDeadline> Latest deadline of each chain. */
    public static function currentDeadlines(ProcurementProject $project): Collection
    {
        return ProcurementDeadline::where('procurement_project_id', $project->id)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('procurement_deadlines as later')->whereColumn('later.extended_from_id', 'procurement_deadlines.id'))
            ->orderBy('due_at')->get();
    }

    public function recordPublication(User $actor, ProcurementProject $project, string $channel, string $postedAt, ?string $reference = null, ?Document $document = null): ProcurementPublication
    {
        ProcurementGuard::require($actor, 'procurement.manage', $project);
        $this->assertOpen($project);
        if (! array_key_exists($channel, self::CHANNELS)) {
            throw new WorkflowViolation("Unknown channel {$channel}.");
        }

        return ProcurementPublication::create([
            'procurement_project_id' => $project->id, 'channel' => $channel, 'reference_no' => $reference,
            'posted_at' => $postedAt, 'posted_by' => $actor->id, 'document_id' => $document?->id,
        ]);
    }

    public function scheduleEvent(User $actor, ProcurementProject $project, string $type, string $scheduledAt, ?string $venue = null): ProcurementEvent
    {
        ProcurementGuard::require($actor, 'procurement.manage', $project);
        $this->assertOpen($project);
        if (! array_key_exists($type, self::EVENT_TYPES)) {
            throw new WorkflowViolation("Unknown event type {$type}.");
        }

        return ProcurementEvent::create(['procurement_project_id' => $project->id, 'event_type' => $type, 'scheduled_at' => $scheduledAt, 'venue' => $venue]);
    }

    public function recordEventHeld(User $actor, ProcurementEvent $event, string $heldAt, ?string $remarks = null): ProcurementEvent
    {
        ProcurementGuard::require($actor, 'procurement.manage', $event->procurementProject);
        if ($event->held_at !== null) {
            throw new WorkflowViolation('The event is already recorded as held.');
        }
        $event->forceFill(['held_at' => $heldAt, 'remarks' => $remarks, 'recorded_by' => $actor->id])->save();

        return $event;
    }

    /**
     * Cancels the project. Before solicitation, procurement staff may cancel; afterwards only the HoPE.
     * Its purchase request lines become available for a new project; reservations stay with the requests.
     */
    public function cancel(User $actor, ProcurementProject $project, string $reason): ProcurementProject
    {
        $early = in_array($project->status, [ProcurementProjectStatus::Proposed, ProcurementProjectStatus::ModeReview], true);
        ProcurementGuard::require($actor, $early ? 'procurement.manage' : 'procurement_mode.approve', $project);

        return DB::transaction(function () use ($actor, $project, $reason) {
            ($this->transition)($project, ProcurementProjectStatus::Cancelled, $actor, $reason);
            $project->justifications()->whereIn('status', ['proposed', 'endorsed'])->get()
                ->each(fn ($j) => $j->forceFill(['status' => 'rejected', 'decision_remarks' => "Project cancelled: {$reason}"])->save());

            return $project;
        });
    }

    private function assertOpen(ProcurementProject $project): void
    {
        if (in_array($project->status, [...CreateProcurementProject::INACTIVE, ProcurementProjectStatus::Completed], true)) {
            throw new WorkflowViolation("The project is {$project->status->label()}.");
        }
    }
}
