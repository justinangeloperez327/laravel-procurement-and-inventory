<?php

namespace App\Domain\Shared;

use App\Models\StatusHistory;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only sanctioned way to change a workflow status. It locks the record, checks the transition
 * against the status enum's state machine, requires a reason where the target state demands one,
 * and appends to status_histories.
 */
class TransitionStatus
{
    /**
     * @template TModel of Model
     *
     * @param  TModel  $subject
     * @return TModel
     */
    public function __invoke(
        Model $subject,
        HasTransitions&BackedEnum $to,
        ?User $actor = null,
        ?string $reason = null,
        string $attribute = 'status',
    ): Model {
        return DB::transaction(function () use ($subject, $to, $actor, $reason, $attribute) {
            $locked = $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->getAttribute($attribute);

            if (! $from instanceof $to) {
                throw new WorkflowViolation(sprintf('%s.%s is not a %s state.', class_basename($subject), $attribute, class_basename($to)));
            }
            if (! in_array($to, $from->allowedTransitions(), true)) {
                throw new WorkflowViolation(sprintf('%s cannot move from %s to %s.', class_basename($subject), $from->value, $to->value));
            }
            if ($to->requiresReason() && blank($reason)) {
                throw new WorkflowViolation("A reason is required to move to {$to->value}.");
            }

            $locked->forceFill([$attribute => $to])->save();

            StatusHistory::create([
                'subject_type' => $locked->getMorphClass(),
                'subject_id' => $locked->getKey(),
                'from_status' => $from->value,
                'to_status' => $to->value,
                'actor_id' => $actor?->getKey(),
                'reason' => $reason,
                'changed_at' => now(),
            ]);

            $subject->setRawAttributes($locked->getAttributes(), sync: true);

            return $subject;
        });
    }
}
