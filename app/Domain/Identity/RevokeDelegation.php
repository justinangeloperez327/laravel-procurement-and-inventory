<?php

namespace App\Domain\Identity;

use App\Models\AuditLog;
use App\Models\Delegation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RevokeDelegation
{
    public function __invoke(User $actor, Delegation $delegation, string $reason): Delegation
    {
        if (! ($actor->id === $delegation->delegator_id || ($actor->is_admin && $actor->is_active))) {
            throw new IdentityViolation('Only the delegator or a system administrator may revoke a delegation.');
        }
        if (trim($reason) === '') {
            throw new IdentityViolation('A revocation reason is required.');
        }

        return DB::transaction(function () use ($actor, $delegation, $reason) {
            $locked = Delegation::whereKey($delegation->id)->lockForUpdate()->firstOrFail();
            if ($locked->revoked_at !== null) {
                throw new IdentityViolation('This delegation is already revoked.');
            }

            $locked->forceFill([
                'revoked_at' => now(),
                'revoked_by' => $actor->id,
                'revocation_reason' => $reason,
            ])->save();

            AuditLog::record('delegation.revoked', $locked, null, null, ['reason' => $reason]);

            return $locked;
        });
    }
}
