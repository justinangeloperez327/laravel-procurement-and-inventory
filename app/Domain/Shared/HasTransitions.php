<?php

namespace App\Domain\Shared;

/**
 * Implemented by status enums that form a state machine.
 */
interface HasTransitions
{
    /** @return list<static> States reachable from this one. */
    public function allowedTransitions(): array;

    /** Whether entering this state requires a recorded reason (return, rejection, cancellation...). */
    public function requiresReason(): bool;
}
