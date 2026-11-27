<?php

namespace App\Enums;

use App\Domain\Shared\HasTransitions;

/** Purchase request lifecycle (docs/07). */
enum PurchaseRequestStatus: string implements HasTransitions
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case DepartmentReview = 'department_review';
    case BudgetReview = 'budget_review';
    case Approved = 'approved';
    case Procurement = 'procurement';
    case Fulfilled = 'fulfilled';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::DepartmentReview => 'Department review',
            self::BudgetReview => 'Budget review',
            self::Approved => 'Approved',
            self::Procurement => 'Procurement',
            self::Fulfilled => 'Fulfilled',
            self::Returned => 'Returned',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted => [self::DepartmentReview, self::Returned, self::Cancelled],
            self::DepartmentReview => [self::BudgetReview, self::Returned, self::Rejected],
            self::BudgetReview => [self::Approved, self::Returned, self::Rejected],
            self::Approved => [self::Procurement, self::Cancelled],
            self::Procurement => [self::Fulfilled, self::Cancelled],
            self::Returned => [self::Submitted, self::Cancelled],
            self::Fulfilled, self::Rejected, self::Cancelled => [],
        };
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::Returned, self::Rejected, self::Cancelled], true);
    }
}
