<?php

namespace App\Domain\Compliance;

use App\Domain\Shared\WorkflowViolation;
use App\Models\AuditLog;
use App\Models\ComplianceFlag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Raises and resolves compliance flags. A flag of the same type stays single per subject while open,
 * so repeated sweeps do not pile up duplicates. Blocking flags stop the subject (e.g. a supplier)
 * from proceeding until a reviewer clears them.
 */
class ComplianceFlags
{
    public const SEVERITIES = ['info', 'warning', 'blocking'];

    public const OPEN_STATUSES = ['open', 'escalated'];

    public function raise(int $agencyId, Model $subject, string $type, string $severity, string $description, ?User $raisedBy = null): ComplianceFlag
    {
        if (! in_array($severity, self::SEVERITIES, true)) {
            throw new WorkflowViolation("Unknown severity {$severity}.");
        }

        return DB::transaction(function () use ($agencyId, $subject, $type, $severity, $description, $raisedBy) {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["flag:{$subject->getMorphClass()}:{$subject->getKey()}:{$type}"]);

            $existing = ComplianceFlag::query()
                ->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())
                ->where('flag_type', $type)->whereIn('status', self::OPEN_STATUSES)->first();
            if ($existing) {
                return $existing;
            }

            $flag = ComplianceFlag::create([
                'agency_id' => $agencyId,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'flag_type' => $type,
                'severity' => $severity,
                'description' => $description,
                'raised_by' => $raisedBy?->id,
                'raised_at' => now(),
                'status' => 'open',
            ]);
            AuditLog::record('compliance_flag.raised', $flag, null, ['type' => $type, 'severity' => $severity]);

            return $flag;
        });
    }

    /** @param  'reviewed'|'dismissed'|'escalated'  $decision */
    public function review(User $reviewer, ComplianceFlag $flag, string $decision, string $notes): ComplianceFlag
    {
        if (! $reviewer->hasPermission('compliance.review')) {
            throw new WorkflowViolation('You do not hold compliance.review.');
        }
        if (! in_array($decision, ['reviewed', 'dismissed', 'escalated'], true)) {
            throw new WorkflowViolation("Unknown decision {$decision}.");
        }
        if (blank($notes)) {
            throw new WorkflowViolation('Review notes are required.');
        }
        if ($flag->raised_by === $reviewer->id) {
            throw new WorkflowViolation('The person who raised a flag may not review it.');
        }

        return DB::transaction(function () use ($reviewer, $flag, $decision, $notes) {
            $locked = ComplianceFlag::whereKey($flag->id)->lockForUpdate()->firstOrFail();
            if ($locked->agency_id !== $reviewer->agency_id) {
                throw new WorkflowViolation('The flag belongs to another agency.');
            }
            if (! in_array($locked->status, self::OPEN_STATUSES, true)) {
                throw new WorkflowViolation("The flag is already {$locked->status}.");
            }

            $locked->forceFill(['status' => $decision, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_notes' => $notes])->save();
            AuditLog::record('compliance_flag.'.$decision, $locked, null, null, ['notes' => $notes]);

            return $locked;
        });
    }
}
