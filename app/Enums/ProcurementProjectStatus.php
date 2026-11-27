<?php

namespace App\Enums;

use App\Domain\Shared\HasTransitions;

/** Procurement project lifecycle (docs/07). Transitions differ by mode. */
enum ProcurementProjectStatus: string implements HasTransitions
{
    case Proposed = 'proposed';
    case ModeReview = 'mode_review';
    case Solicitation = 'solicitation';
    case Receipt = 'receipt';
    case Evaluation = 'evaluation';
    case PostQualification = 'post_qualification';
    case Recommended = 'recommended';
    case Awarded = 'awarded';
    case Contracted = 'contracted';
    case Implementation = 'implementation';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Proposed',
            self::ModeReview => 'Mode review',
            self::Solicitation => 'Solicitation',
            self::Receipt => 'Receipt',
            self::Evaluation => 'Evaluation',
            self::PostQualification => 'Post qualification',
            self::Recommended => 'Recommended',
            self::Awarded => 'Awarded',
            self::Contracted => 'Contracted',
            self::Implementation => 'Implementation',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Proposed => [self::ModeReview, self::Cancelled],
            self::ModeReview => [self::Solicitation, self::Proposed, self::Cancelled],
            self::Solicitation => [self::Receipt, self::Failed, self::Cancelled],
            self::Receipt => [self::Evaluation, self::Failed, self::Cancelled],
            self::Evaluation => [self::PostQualification, self::Failed, self::Cancelled],
            // A failed post-qualification passes to the next-ranked bidder.
            self::PostQualification => [self::Recommended, self::Evaluation, self::Failed, self::Cancelled],
            self::Recommended => [self::Awarded, self::Failed, self::Cancelled],
            self::Awarded => [self::Contracted, self::Cancelled],
            self::Contracted => [self::Implementation],
            self::Implementation => [self::Completed],
            // A failed procurement is re-bid or routed to an alternative mode after review.
            self::Failed => [self::ModeReview, self::Solicitation, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::Failed, self::Cancelled, self::Proposed], true);
    }
}
