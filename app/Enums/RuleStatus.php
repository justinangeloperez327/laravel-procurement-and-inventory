<?php

namespace App\Enums;

/** Regulatory rule version status. Only approved rules may be enforced. */
enum RuleStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Retired => 'Retired',
        };
    }
}
