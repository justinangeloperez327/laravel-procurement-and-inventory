<?php

namespace App\Domain\Procurement;

/**
 * Outcome of screening a procurement mode for a project.
 *
 * @phpstan-type Check array{rule_code: string, version: int|null, rule_type: string, blocking: bool, passed: bool, message: string}
 */
final readonly class ScreeningResult
{
    /** @param  list<Check>  $checks */
    public function __construct(public string $mode, public string $governingDate, public array $checks) {}

    /** @return list<Check> */
    public function blockingFailures(): array
    {
        return array_values(array_filter($this->checks, fn (array $c) => $c['blocking'] && ! $c['passed']));
    }

    /** @return list<Check> */
    public function warnings(): array
    {
        return array_values(array_filter($this->checks, fn (array $c) => ! $c['blocking'] && ! $c['passed']));
    }

    public function eligible(): bool
    {
        return $this->blockingFailures() === [];
    }

    /** The ceiling from the first passing abc_max condition, in centavos, if any (used for splitting checks). */
    public function ceilingCents(): ?int
    {
        foreach ($this->checks as $check) {
            if (isset($check['abc_max_cents']) && $check['passed']) {
                return $check['abc_max_cents'];
            }
        }

        return null;
    }

    public function toArray(): array
    {
        return ['mode' => $this->mode, 'governing_date' => $this->governingDate, 'checks' => $this->checks];
    }
}
