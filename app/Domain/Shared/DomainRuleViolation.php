<?php

namespace App\Domain\Shared;

use DomainException;

/** Base class for business-rule violations that should be reported to the user, not as a server error. */
class DomainRuleViolation extends DomainException {}
