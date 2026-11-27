<?php

namespace App\Enums;

/** Supplier sanction type. */
enum SanctionType: string
{
    case Suspension = 'suspension';
    case Blacklisting = 'blacklisting';

    public function label(): string
    {
        return match ($this) {
            self::Suspension => 'Suspension',
            self::Blacklisting => 'Blacklisting',
        };
    }
}
