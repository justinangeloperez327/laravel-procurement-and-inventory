<?php

namespace App\Domain\Budget;

use App\Domain\Shared\Money;

/**
 * Balance of one allocation, in integer centavos (docs/09):
 *   adjusted  = allotted + augmentations + realigned in - reductions - realigned out
 *   available = adjusted - active reservations - active obligations
 *   unpaid    = active obligations - disbursements
 * Disbursements reduce unpaid obligations only; they are never subtracted from available again.
 */
final readonly class BudgetBalance
{
    public function __construct(
        public int $allotted,
        public int $augmentations,
        public int $reductions,
        public int $realignedIn,
        public int $realignedOut,
        public int $reserved,
        public int $obligated,
        public int $disbursed,
    ) {}

    public function adjusted(): int
    {
        return $this->allotted + $this->augmentations + $this->realignedIn - $this->reductions - $this->realignedOut;
    }

    public function available(): int
    {
        return $this->adjusted() - $this->reserved - $this->obligated;
    }

    public function unpaidObligations(): int
    {
        return $this->obligated - $this->disbursed;
    }

    /** @return array<string, string> Formatted for display. */
    public function toDisplay(): array
    {
        return [
            'Allotted' => Money::format(Money::fromCents($this->allotted)),
            'Augmentations' => Money::format(Money::fromCents($this->augmentations)),
            'Reductions' => Money::format(Money::fromCents(-$this->reductions)),
            'Realigned in' => Money::format(Money::fromCents($this->realignedIn)),
            'Realigned out' => Money::format(Money::fromCents(-$this->realignedOut)),
            'Adjusted allotment' => Money::format(Money::fromCents($this->adjusted())),
            'Reserved' => Money::format(Money::fromCents($this->reserved)),
            'Obligated' => Money::format(Money::fromCents($this->obligated)),
            'Available' => Money::format(Money::fromCents($this->available())),
            'Disbursed' => Money::format(Money::fromCents($this->disbursed)),
            'Unpaid obligations' => Money::format(Money::fromCents($this->unpaidObligations())),
        ];
    }
}
