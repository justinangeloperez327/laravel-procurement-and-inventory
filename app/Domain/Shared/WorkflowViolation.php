<?php

namespace App\Domain\Shared;

/** Raised when a workflow step (transition, approval, posting) is not allowed. */
class WorkflowViolation extends DomainRuleViolation {}
