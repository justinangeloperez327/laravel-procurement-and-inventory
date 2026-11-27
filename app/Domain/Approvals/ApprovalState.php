<?php

namespace App\Domain\Approvals;

use App\Models\ApprovalAction;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use Illuminate\Support\Collection;

/** Where a record stands in its current approval round. */
final readonly class ApprovalState
{
    /**
     * @param  Collection<int, ApprovalAction>  $roundActions  Actions since the last return.
     */
    public function __construct(
        public ApprovalWorkflow $workflow,
        public ApprovalOutcome $outcome,
        public ?ApprovalStep $nextStep,
        public Collection $roundActions,
    ) {}

    public function isOpen(): bool
    {
        return in_array($this->outcome, [ApprovalOutcome::Pending, ApprovalOutcome::Returned], true);
    }
}
