<?php

namespace App\Enums;

/** Supplier invoice lifecycle. */
enum InvoiceStatus: string
{
    case Received = 'received';
    case Matched = 'matched';
    case Disputed = 'disputed';
    case ApprovedForPayment = 'approved_for_payment';
    case Paid = 'paid';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Matched => 'Matched',
            self::Disputed => 'Disputed',
            self::ApprovedForPayment => 'Approved for payment',
            self::Paid => 'Paid',
            self::Rejected => 'Rejected',
        };
    }
}
