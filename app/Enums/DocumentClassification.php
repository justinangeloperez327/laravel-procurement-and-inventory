<?php

namespace App\Enums;

/** Access classification of a stored document. */
enum DocumentClassification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Confidential = 'confidential';
    case SealedBid = 'sealed_bid';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Internal => 'Internal',
            self::Confidential => 'Confidential',
            self::SealedBid => 'Sealed bid',
        };
    }
}
