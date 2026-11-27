<?php

namespace App\Enums;

/** Delivery lifecycle (docs/07). */
enum DeliveryStatus: string
{
    case Scheduled = 'scheduled';
    case Received = 'received';
    case InspectionPending = 'inspection_pending';
    case Accepted = 'accepted';
    case PartiallyAccepted = 'partially_accepted';
    case Rejected = 'rejected';
    case Posted = 'posted';
    case DiscrepancyFollowup = 'discrepancy_followup';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Received => 'Received',
            self::InspectionPending => 'Inspection pending',
            self::Accepted => 'Accepted',
            self::PartiallyAccepted => 'Partially accepted',
            self::Rejected => 'Rejected',
            self::Posted => 'Posted',
            self::DiscrepancyFollowup => 'Discrepancy followup',
        };
    }
}
