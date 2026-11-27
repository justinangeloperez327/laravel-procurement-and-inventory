<?php

namespace App\Enums;

use App\Domain\Shared\HasTransitions;

/** Status of market scopings, PPMPs and APP versions. */
enum PlanStatus: string implements HasTransitions
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Approved = 'approved';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Returned => 'Returned',
            self::Approved => 'Approved',
            self::Superseded => 'Superseded',
        };
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted => [self::Approved, self::Returned],
            self::Returned => [self::Submitted],
            self::Approved => [self::Superseded],
            self::Superseded => [],
        };
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::Returned], true);
    }
}
