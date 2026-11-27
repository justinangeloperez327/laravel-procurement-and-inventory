<?php

namespace App\Domain\Bac;

use App\Domain\Shared\ReferenceNumbers;
use App\Domain\Shared\WorkflowViolation;
use App\Models\AuditLog;
use App\Models\BacCommittee;
use App\Models\BacResolution;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\ProcurementProject;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * BAC meetings, attendance, quorum and resolutions (L-06).
 *
 * Quorum (default, to be confirmed against the current IRR): a majority of the voting members serving
 * on the meeting date are present, and the chairperson or vice-chairperson presides. Resolutions can
 * only be adopted at a held meeting with quorum, by a voting member who was present.
 */
class BacMeetings
{
    public function __construct(private ReferenceNumbers $numbers) {}

    public function schedule(User $actor, BacCommittee $committee, string $type, string $scheduledAt, ?ProcurementProject $project = null, ?string $venue = null): Meeting
    {
        $this->requireMeetingRights($actor, $committee);
        if ($committee->dissolved_on !== null) {
            throw new WorkflowViolation('The committee has been dissolved.');
        }

        return Meeting::create([
            'bac_committee_id' => $committee->id,
            'procurement_project_id' => $project?->id,
            'meeting_type' => $type,
            'scheduled_at' => $scheduledAt,
            'venue' => $venue,
        ]);
    }

    /**
     * Records who attended and closes the meeting, computing quorum.
     *
     * @param  list<int>  $presentUserIds  Committee members and TWG members present.
     * @param  list<string>  $observers  Names of observers present.
     */
    public function recordHeld(User $actor, Meeting $meeting, array $presentUserIds, array $observers = [], ?string $heldAt = null): Meeting
    {
        $this->requireMeetingRights($actor, $meeting->bacCommittee);

        return DB::transaction(function () use ($meeting, $presentUserIds, $observers, $heldAt) {
            $locked = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            if ($locked->held_at !== null) {
                throw new WorkflowViolation('Attendance for this meeting is already recorded.');
            }
            $held = Carbon::parse($heldAt ?? now());
            $voting = BacComposition::votingMembers($locked->bacCommittee, $held);
            $members = $locked->bacCommittee->members()->get()->keyBy('user_id');

            foreach (array_unique($presentUserIds) as $userId) {
                $role = $members[$userId]->member_role ?? ($locked->bacCommittee->twgMembers()->where('user_id', $userId)->exists() ? 'twg' : null);
                if ($role === null) {
                    throw new WorkflowViolation("User #{$userId} is not on this committee; record them as an observer.");
                }
                MeetingAttendee::create(['meeting_id' => $locked->id, 'user_id' => $userId, 'attendee_role' => $role, 'present' => true]);
            }
            foreach ($voting->whereNotIn('user_id', $presentUserIds) as $absent) {
                MeetingAttendee::create(['meeting_id' => $locked->id, 'user_id' => $absent->user_id, 'attendee_role' => $absent->member_role, 'present' => false]);
            }
            foreach (array_filter(array_map('trim', $observers)) as $name) {
                MeetingAttendee::create(['meeting_id' => $locked->id, 'name' => $name, 'attendee_role' => 'observer', 'present' => true]);
            }

            $presentVoting = $voting->whereIn('user_id', $presentUserIds);
            $presiding = $presentVoting->contains(fn ($m) => in_array($m->member_role, ['chair', 'vice_chair'], true));
            $quorum = $voting->isNotEmpty() && $presentVoting->count() > intdiv($voting->count(), 2) && $presiding;

            $locked->forceFill(['held_at' => $held, 'quorum_met' => $quorum])->save();
            AuditLog::record('bac.meeting_held', $locked, null, [
                'voting_members' => $voting->count(), 'present' => $presentVoting->count(), 'quorum_met' => $quorum,
            ]);

            return $locked;
        });
    }

    public function adoptResolution(User $actor, Meeting $meeting, string $type, string $title, ?string $body = null, ?ProcurementProject $project = null): BacResolution
    {
        if (! $actor->hasPermission('bac.recommend')) {
            throw new WorkflowViolation('You do not hold bac.recommend.');
        }

        return DB::transaction(function () use ($actor, $meeting, $type, $title, $body, $project) {
            $locked = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            if ($locked->held_at === null) {
                throw new WorkflowViolation('Record the meeting attendance before adopting resolutions.');
            }
            if (! $locked->quorum_met) {
                throw new WorkflowViolation('The meeting had no quorum; resolutions cannot be adopted.');
            }
            $wasPresent = $locked->attendees()->where('user_id', $actor->id)->where('present', true)
                ->whereIn('attendee_role', BacComposition::VOTING_ROLES)->exists();
            if (! $wasPresent) {
                throw new WorkflowViolation('Only a voting member present at the meeting may record its resolutions.');
            }
            $committee = $locked->bacCommittee;
            if ($project !== null && $project->agency_id !== $committee->agency_id) {
                throw new WorkflowViolation('The project belongs to another agency.');
            }

            $resolution = BacResolution::create([
                'bac_committee_id' => $committee->id,
                'procurement_project_id' => $project?->id ?? $locked->procurement_project_id,
                'meeting_id' => $locked->id,
                'resolution_no' => $this->numbers->next($committee->agency_id, 'BACRES', (int) $locked->held_at->format('Y')),
                'title' => $title,
                'resolution_type' => $type,
                'body' => $body,
                'adopted_on' => $locked->held_at->toDateString(),
                'recorded_by' => $actor->id,
            ]);
            AuditLog::record('bac.resolution_adopted', $resolution, null, ['type' => $type, 'no' => $resolution->resolution_no]);

            return $resolution;
        });
    }

    private function requireMeetingRights(User $actor, BacCommittee $committee): void
    {
        if (! $actor->hasPermission('bac.meeting')) {
            throw new WorkflowViolation('You do not hold bac.meeting.');
        }
        if ($committee->agency_id !== $actor->agency_id) {
            throw new WorkflowViolation('The committee belongs to another agency.');
        }
    }
}
