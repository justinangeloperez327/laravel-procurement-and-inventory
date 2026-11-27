<?php

namespace App\Enums;

/** Status of market scopings, PPMPs and APP versions. */
enum PlanStatus: string
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
}
