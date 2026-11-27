<?php

namespace App\Enums;

/** Purchase request lifecycle (docs/07). */
enum PurchaseRequestStatus: string
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
}
