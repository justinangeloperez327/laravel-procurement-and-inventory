<?php

namespace App\Domain\Budget;

use App\Domain\Shared\Money;
use App\Domain\Shared\WorkflowViolation;
use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\BudgetReservation;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reserves allotment balance for an approved purchase request and releases it on cancellation,
 * rejection or failure (docs/09). The allotment row is locked so concurrent certifications cannot
 * overcommit it, and each request carries an idempotency key.
 */
class BudgetReservations
{
    public function __construct(private BudgetLedger $ledger) {}

    public function reserve(User $actor, BudgetAllocation $allocation, PurchaseRequest $request, string $idempotencyKey): BudgetReservation
    {
        if (! $actor->hasPermission('budget.reserve')) {
            throw new WorkflowViolation('You do not hold budget.reserve.');
        }

        return DB::transaction(function () use ($actor, $allocation, $request, $idempotencyKey) {
            if ($existing = BudgetReservation::where('idempotency_key', $idempotencyKey)->first()) {
                if ($existing->purchase_request_id !== $request->id || $existing->budget_allocation_id !== $allocation->id) {
                    throw new WorkflowViolation('This idempotency key was already used for a different reservation.');
                }

                return $existing;
            }

            $locked = BudgetAllocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            $this->assertMatches($actor, $locked, $request);

            if ($request->reservations()->where('status', BudgetReservation::STATUS_ACTIVE)->exists()) {
                throw new WorkflowViolation("{$request->reference_no} already has an active reservation.");
            }

            $amount = Money::toCents((string) $request->total_amount);
            if ($amount <= 0) {
                throw new WorkflowViolation('There is nothing to reserve.');
            }
            $available = $this->ledger->balance($locked)->available();
            if ($amount > $available) {
                throw new WorkflowViolation(sprintf('Insufficient funds: %s requested, %s available in %s.',
                    Money::format(Money::fromCents($amount)), Money::format(Money::fromCents($available)), $locked->reference_no));
            }

            $reservation = BudgetReservation::create([
                'budget_allocation_id' => $locked->id,
                'purchase_request_id' => $request->id,
                'amount' => Money::fromCents($amount),
                'status' => BudgetReservation::STATUS_ACTIVE,
                'reserved_by' => $actor->id,
                'reserved_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);
            AuditLog::record('budget.reserved', $reservation, null, ['amount' => $reservation->amount, 'purchase_request' => $request->reference_no]);

            return $reservation;
        });
    }

    public function release(?User $actor, BudgetReservation $reservation, string $reason): BudgetReservation
    {
        if (blank($reason)) {
            throw new WorkflowViolation('A reason is required to release a reservation.');
        }

        return DB::transaction(function () use ($actor, $reservation, $reason) {
            $locked = BudgetReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== BudgetReservation::STATUS_ACTIVE) {
                throw new WorkflowViolation("The reservation is already {$locked->status}.");
            }

            $locked->forceFill([
                'status' => BudgetReservation::STATUS_RELEASED,
                'released_at' => now(),
                'released_by' => $actor?->id,
                'release_reason' => $reason,
            ])->save();
            AuditLog::record('budget.released', $locked, null, ['amount' => $locked->amount], ['reason' => $reason]);

            return $locked;
        });
    }

    /** The allotment must be approved and match the request's agency, fiscal year, department, fund and account. */
    private function assertMatches(User $actor, BudgetAllocation $allocation, PurchaseRequest $request): void
    {
        $problems = array_filter([
            $allocation->agency_id !== $actor->agency_id || $allocation->agency_id !== $request->agency_id ? 'belongs to another agency' : null,
            ! $allocation->isApproved() ? 'is not approved' : null,
            $allocation->fiscal_year_id !== $request->fiscal_year_id ? 'is for another fiscal year' : null,
            $allocation->department_id !== null && $allocation->department_id !== $request->department_id ? 'is earmarked for another department' : null,
            $request->fund_source_id !== null && $allocation->fund_source_id !== $request->fund_source_id ? 'is from another fund source' : null,
            $request->account_code_id !== null && $allocation->account_code_id !== $request->account_code_id ? 'is for another account code' : null,
        ]);
        if ($problems !== []) {
            throw new WorkflowViolation("Allotment {$allocation->reference_no} ".implode(', ', $problems).'.');
        }
    }
}
