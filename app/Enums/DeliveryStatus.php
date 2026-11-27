<?php

namespace App\Enums;

use App\Domain\Shared\HasTransitions;

/** Delivery lifecycle (docs/07). */
enum DeliveryStatus: string implements HasTransitions
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

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Scheduled => [self::Received],
            self::Received => [self::InspectionPending],
            self::InspectionPending => [self::Accepted, self::PartiallyAccepted, self::Rejected],
            self::Accepted => [self::Posted],
            self::PartiallyAccepted => [self::Posted, self::DiscrepancyFollowup],
            self::Rejected => [self::DiscrepancyFollowup],
            self::DiscrepancyFollowup => [self::Posted],
            self::Posted => [],
        };
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::Rejected, self::DiscrepancyFollowup], true);
    }
}
