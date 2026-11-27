<?php

namespace App\Domain\Identity;

use DomainException;

/** Raised when an identity or authority change would breach an access-control rule. */
class IdentityViolation extends DomainException {}
