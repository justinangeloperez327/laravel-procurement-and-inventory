<?php

namespace App\Enums;

/** Purchase order lifecycle. */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Acknowledged = 'acknowledged';
    case PartiallyDelivered = 'partially_delivered';
    case Delivered = 'delivered';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::Acknowledged => 'Acknowledged',
            self::PartiallyDelivered => 'Partially delivered',
            self::Delivered => 'Delivered',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }
}
