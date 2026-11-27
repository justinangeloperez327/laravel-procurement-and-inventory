<?php

namespace App\Domain\Approvals;

enum ApprovalOutcome: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Returned = 'returned';
    case Rejected = 'rejected';
}
