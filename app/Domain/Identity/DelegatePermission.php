<?php

namespace App\Domain\Identity;

use App\Models\AuditLog;
use App\Models\Delegation;
use App\Models\Department;
use App\Models\Permission;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * A holder of a permission delegates it, for a bounded period, to another user in the same agency.
 */
class DelegatePermission
{
    public function __construct(private Authorizer $authorizer) {}

    public function __invoke(
        User $delegator,
        User $delegate,
        Permission $permission,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        string $reason,
        ?Department $department = null,
    ): Delegation {
        if ($delegator->is($delegate)) {
            throw new IdentityViolation('A permission cannot be delegated to oneself.');
        }
        if (! $delegate->is_active) {
            throw new IdentityViolation('The delegate is inactive.');
        }
        if ($delegator->agency_id === null || $delegate->agency_id !== $delegator->agency_id) {
            throw new IdentityViolation('Delegation is limited to users of the same agency.');
        }
        if ($department !== null && $department->agency_id !== $delegator->agency_id) {
            throw new IdentityViolation('The department belongs to a different agency.');
        }
        if ($endsAt->lte($startsAt)) {
            throw new IdentityViolation('The delegation must end after it starts.');
        }
        if ($endsAt->isPast()) {
            throw new IdentityViolation('The delegation period has already ended.');
        }
        if (trim($reason) === '') {
            throw new IdentityViolation('A reason for the delegation is required.');
        }
        if (! $this->authorizer->holdsDirectly($delegator, $permission->code, $delegator->agency_id, $department, $startsAt)) {
            throw new IdentityViolation('Only authority held directly through a role may be delegated.');
        }

        $this->assertSegregation($delegate, $permission);

        return DB::transaction(function () use ($delegator, $delegate, $permission, $startsAt, $endsAt, $reason, $department) {
            $delegation = Delegation::create([
                'delegator_id' => $delegator->id,
                'delegate_id' => $delegate->id,
                'permission_id' => $permission->id,
                'agency_id' => $delegator->agency_id,
                'department_id' => $department?->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'reason' => $reason,
                'created_by' => $delegator->id,
            ]);

            AuditLog::record('delegation.created', $delegation, null, [
                'delegator_id' => $delegator->id,
                'delegate_id' => $delegate->id,
                'permission' => $permission->code,
                'department_id' => $department?->id,
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
            ], ['reason' => $reason]);

            return $delegation;
        });
    }

    /** The delegate must not hold a role incompatible with every role that confers this permission. */
    private function assertSegregation(User $delegate, Permission $permission): void
    {
        $granting = RoleCatalog::rolesGranting($permission->code);
        if ($granting === []) {
            return;
        }

        $held = $delegate->roleAssignments()->active()->with('role')->get()->pluck('role.code')->unique();

        foreach ($held as $code) {
            $conflictsWithAll = collect($granting)->every(fn (string $g) => RoleCatalog::conflicts($code, $g));
            if ($conflictsWithAll) {
                throw new IdentityViolation("The delegate's role ({$code}) is incompatible with {$permission->code}.");
            }
        }
    }
}
