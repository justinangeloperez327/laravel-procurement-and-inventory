<?php

namespace App\Enums;

/** Decision recorded on an approval step. */
enum ApprovalDecision: string
{
    case Endorsed = 'endorsed';
    case Approved = 'approved';
    case Returned = 'returned';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Endorsed => 'Endorsed',
            self::Approved => 'Approved',
            self::Returned => 'Returned',
            self::Rejected => 'Rejected',
        };
    }
}
