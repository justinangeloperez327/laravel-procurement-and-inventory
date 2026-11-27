<?php

namespace App\Enums;

/** Contract lifecycle. */
enum ContractStatus: string
{
    case Draft = 'draft';
    case Signed = 'signed';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Terminated = 'terminated';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Signed => 'Signed',
            self::Ongoing => 'Ongoing',
            self::Completed => 'Completed',
            self::Terminated => 'Terminated',
            self::Cancelled => 'Cancelled',
        };
    }
}
