<?php

namespace App\Domain\Bac;

use App\Domain\Identity\RoleCatalog;
use App\Domain\Shared\WorkflowViolation;
use App\Models\AuditLog;
use App\Models\BacCommittee;
use App\Models\BacMember;
use App\Models\TwgMember;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * BAC constitution by the HoPE (L-06). A membership must match the person's role: the chair holds
 * the BAC Chair role, members the BAC Member or Chair role, the secretariat the BAC Secretariat
 * role. Role segregation (HoPE vs BAC) is enforced when roles are granted (phase 1).
 */
class BacComposition
{
    public const VOTING_ROLES = ['chair', 'vice_chair', 'member', 'provisional_member'];

    public const MEMBER_ROLES = [
        'chair' => ['label' => 'Chairperson', 'roles' => ['bac_chair']],
        'vice_chair' => ['label' => 'Vice-chairperson', 'roles' => ['bac_chair', 'bac_member']],
        'member' => ['label' => 'Member', 'roles' => ['bac_member', 'bac_chair']],
        'provisional_member' => ['label' => 'Provisional member', 'roles' => ['bac_member']],
        'secretariat' => ['label' => 'Secretariat', 'roles' => ['bac_secretariat']],
    ];

    public function constitute(User $hope, string $name, string $type, string $officeOrder, ?string $constitutedOn = null): BacCommittee
    {
        $this->requireConstitute($hope);
        if (blank($officeOrder)) {
            throw new WorkflowViolation('The office order constituting the BAC is required.');
        }

        return BacCommittee::create([
            'agency_id' => $hope->agency_id,
            'name' => $name,
            'committee_type' => $type,
            'constituted_on' => $constitutedOn ?? now()->toDateString(),
            'office_order_reference' => $officeOrder,
        ]);
    }

    public function addMember(User $hope, BacCommittee $committee, User $member, string $memberRole, ?string $designation = null, ?string $startsOn = null, ?string $endsOn = null): BacMember
    {
        $this->requireConstitute($hope, $committee);
        if (! array_key_exists($memberRole, self::MEMBER_ROLES)) {
            throw new WorkflowViolation("Unknown BAC role {$memberRole}.");
        }
        if ($member->agency_id !== $committee->agency_id || ! $member->is_active) {
            throw new WorkflowViolation('Members must be active users of the same agency.');
        }
        $held = $member->roleAssignments()->active()->with('role')->get()->pluck('role.code');
        if ($held->intersect(self::MEMBER_ROLES[$memberRole]['roles'])->isEmpty()) {
            throw new WorkflowViolation(sprintf('%s needs the %s role to serve as %s.', $member->name,
                implode(' or ', array_map(fn ($r) => RoleCatalog::ROLES[$r]['name'], self::MEMBER_ROLES[$memberRole]['roles'])),
                strtolower(self::MEMBER_ROLES[$memberRole]['label'])));
        }

        return DB::transaction(function () use ($committee, $member, $memberRole, $designation, $startsOn, $endsOn) {
            BacCommittee::whereKey($committee->id)->lockForUpdate()->first();
            $start = Carbon::parse($startsOn ?? now())->toDateString();
            $current = fn () => BacMember::where('bac_committee_id', $committee->id)
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start));

            if ($current()->where('user_id', $member->id)->exists()) {
                throw new WorkflowViolation("{$member->name} is already a member of this committee.");
            }
            if ($memberRole === 'chair' && $current()->where('member_role', 'chair')->exists()) {
                throw new WorkflowViolation('The committee already has a chairperson. End that designation first.');
            }

            $record = BacMember::create([
                'bac_committee_id' => $committee->id,
                'user_id' => $member->id,
                'member_role' => $memberRole,
                'designation_reference' => $designation,
                'starts_on' => $start,
                'ends_on' => $endsOn,
            ]);
            AuditLog::record('bac.member_designated', $record, null, ['user_id' => $member->id, 'role' => $memberRole]);

            return $record;
        });
    }

    public function endMembership(User $hope, BacMember $membership, string $endsOn, string $reason): BacMember
    {
        $this->requireConstitute($hope, $membership->bacCommittee);
        if (blank($reason)) {
            throw new WorkflowViolation('A reason is required.');
        }
        $membership->forceFill(['ends_on' => $endsOn])->save();
        AuditLog::record('bac.member_ended', $membership, null, ['ends_on' => $endsOn], ['reason' => $reason]);

        return $membership;
    }

    public function addTwgMember(User $hope, BacCommittee $committee, User $member, ?string $expertise = null): TwgMember
    {
        $this->requireConstitute($hope, $committee);
        if (! $member->roleAssignments()->active()->whereHas('role', fn ($q) => $q->where('code', 'twg_member'))->exists()) {
            throw new WorkflowViolation("{$member->name} needs the TWG Member role.");
        }

        return TwgMember::create([
            'bac_committee_id' => $committee->id, 'user_id' => $member->id, 'expertise' => $expertise, 'starts_on' => now()->toDateString(),
        ]);
    }

    /** @return Collection<int, BacMember> Voting members serving on the date. */
    public static function votingMembers(BacCommittee $committee, Carbon $on): Collection
    {
        return BacMember::query()
            ->where('bac_committee_id', $committee->id)
            ->whereIn('member_role', self::VOTING_ROLES)
            ->whereDate('starts_on', '<=', $on)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $on))
            ->get();
    }

    private function requireConstitute(User $hope, ?BacCommittee $committee = null): void
    {
        if (! $hope->hasPermission('bac.constitute')) {
            throw new WorkflowViolation('You do not hold bac.constitute.');
        }
        if ($committee !== null && $committee->agency_id !== $hope->agency_id) {
            throw new WorkflowViolation('The committee belongs to another agency.');
        }
    }
}
