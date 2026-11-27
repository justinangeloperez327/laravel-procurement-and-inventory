<?php

namespace App\Domain\Budget;

use App\Domain\Shared\Money;
use App\Models\BudgetAllocation;
use Illuminate\Support\Facades\DB;

/** Computes allocation balances from the ledger tables with exact SQL decimal sums. */
class BudgetLedger
{
    public function balance(BudgetAllocation $allocation): BudgetBalance
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT
                a.amount AS allotted,
                COALESCE((SELECT SUM(amount) FROM budget_adjustments WHERE budget_allocation_id = a.id AND adjustment_type = 'augmentation'), 0) AS augmentations,
                COALESCE((SELECT SUM(amount) FROM budget_adjustments WHERE budget_allocation_id = a.id AND adjustment_type = 'reduction'), 0) AS reductions,
                COALESCE((SELECT SUM(amount) FROM budget_adjustments WHERE budget_allocation_id = a.id AND adjustment_type = 'realignment_in'), 0) AS realigned_in,
                COALESCE((SELECT SUM(amount) FROM budget_adjustments WHERE budget_allocation_id = a.id AND adjustment_type = 'realignment_out'), 0) AS realigned_out,
                COALESCE((SELECT SUM(amount) FROM budget_reservations WHERE budget_allocation_id = a.id AND status = 'active'), 0) AS reserved,
                COALESCE((SELECT SUM(amount) FROM budget_obligations WHERE budget_allocation_id = a.id AND status = 'active'), 0) AS obligated,
                COALESCE((SELECT SUM(d.amount) FROM budget_disbursements d JOIN budget_obligations o ON o.id = d.budget_obligation_id
                          WHERE o.budget_allocation_id = a.id), 0) AS disbursed
            FROM budget_allocations a
            WHERE a.id = ?
        SQL, [$allocation->id]);

        return new BudgetBalance(
            Money::toCents((string) $row->allotted),
            Money::toCents((string) $row->augmentations),
            Money::toCents((string) $row->reductions),
            Money::toCents((string) $row->realigned_in),
            Money::toCents((string) $row->realigned_out),
            Money::toCents((string) $row->reserved),
            Money::toCents((string) $row->obligated),
            Money::toCents((string) $row->disbursed),
        );
    }
}
