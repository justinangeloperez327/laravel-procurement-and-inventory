<?php

namespace App\Domain\Identity;

use App\Models\AuditLog;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RevokeRole
{
    public function __invoke(User $actor, RoleAssignment $assignment, string $reason): RoleAssignment
    {
        if (! $actor->is_admin || ! $actor->is_active) {
            throw new IdentityViolation('Only an active system administrator may revoke roles.');
        }
        if (trim($reason) === '') {
            throw new IdentityViolation('A revocation reason is required.');
        }

        return DB::transaction(function () use ($actor, $assignment, $reason) {
            $locked = RoleAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            if ($locked->revoked_at !== null) {
                throw new IdentityViolation('This role assignment is already revoked.');
            }

            $locked->forceFill([
                'revoked_at' => now(),
                'revoked_by' => $actor->id,
                'revocation_reason' => $reason,
            ])->save();

            AuditLog::record('role.revoked', $locked, null, null, ['reason' => $reason]);

            return $locked;
        });
    }
}
