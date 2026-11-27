<?php

namespace App\Domain\Suppliers;

use App\Domain\Shared\WorkflowViolation;

final readonly class EligibilityResult
{
    /** @param  list<string>  $reasons  Why the supplier is not eligible (empty when eligible). */
    public function __construct(public array $reasons) {}

    public function eligible(): bool
    {
        return $this->reasons === [];
    }

    public function assert(string $supplierName): void
    {
        if (! $this->eligible()) {
            throw new WorkflowViolation("{$supplierName} is not eligible: ".implode('; ', $this->reasons));
        }
    }
}
