<?php

namespace App\Domain\Identity;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class GrantRole
{
    public function __invoke(
        User $actor,
        User $user,
        Role $role,
        ?Department $department = null,
        ?CarbonInterface $expiresAt = null,
        ?string $reason = null,
    ): RoleAssignment {
        if (! $actor->is_admin || ! $actor->is_active) {
            throw new IdentityViolation('Only an active system administrator may grant roles.');
        }
        if ($actor->is($user)) {
            throw new IdentityViolation('Administrators may not grant roles to themselves.');
        }
        if (! $user->is_active) {
            throw new IdentityViolation('Roles cannot be granted to an inactive user.');
        }
        if ($user->agency_id === null) {
            throw new IdentityViolation('The user must belong to an agency before roles can be granted.');
        }
        if ($department !== null && $department->agency_id !== $user->agency_id) {
            throw new IdentityViolation('The department belongs to a different agency.');
        }
        if ($expiresAt !== null && $expiresAt->isPast()) {
            throw new IdentityViolation('The expiry must be in the future.');
        }

        return DB::transaction(function () use ($actor, $user, $role, $department, $expiresAt, $reason) {
            // Serialize grants per user so concurrent requests cannot bypass the conflict checks.
            User::whereKey($user->id)->lockForUpdate()->first();

            $active = $user->roleAssignments()->active()->where('agency_id', $user->agency_id)->with('role')->get();

            foreach ($active as $existing) {
                if (RoleCatalog::conflicts($existing->role->code, $role->code)) {
                    throw new IdentityViolation("{$role->name} is incompatible with the user's active {$existing->role->name} role.");
                }
                if ($existing->role_id === $role->id && $existing->department_id === $department?->id) {
                    throw new IdentityViolation('The user already holds this role in this scope.');
                }
            }

            $assignment = RoleAssignment::create([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'agency_id' => $user->agency_id,
                'department_id' => $department?->id,
                'granted_by' => $actor->id,
                'granted_at' => now(),
                'expires_at' => $expiresAt,
                'reason' => $reason,
            ]);

            AuditLog::record('role.granted', $assignment, null, [
                'user_id' => $user->id,
                'role' => $role->code,
                'department_id' => $department?->id,
                'expires_at' => $expiresAt?->toIso8601String(),
            ], array_filter(['reason' => $reason]));

            return $assignment;
        });
    }
}
