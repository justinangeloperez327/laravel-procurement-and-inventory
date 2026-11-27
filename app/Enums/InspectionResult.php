<?php

namespace App\Enums;

/** Outcome of an inspection. */
enum InspectionResult: string
{
    case Accepted = 'accepted';
    case PartiallyAccepted = 'partially_accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Accepted',
            self::PartiallyAccepted => 'Partially accepted',
            self::Rejected => 'Rejected',
        };
    }
}
