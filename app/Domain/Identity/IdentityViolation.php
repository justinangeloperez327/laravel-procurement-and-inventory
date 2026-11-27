<?php

namespace App\Domain\Identity;

use App\Domain\Shared\DomainRuleViolation;

/** Raised when an identity or authority change would breach an access-control rule. */
class IdentityViolation extends DomainRuleViolation {}
