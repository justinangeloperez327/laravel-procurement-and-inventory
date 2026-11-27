<?php

namespace App\Enums;

/** Procurement project lifecycle (docs/07). Transitions differ by mode. */
enum ProcurementProjectStatus: string
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
}
