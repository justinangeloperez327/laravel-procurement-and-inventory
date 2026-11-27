<?php

namespace App\Domain\Budget;

use App\Domain\Planning\PlanGuards;
use App\Domain\Shared\Money;
use App\Domain\Shared\WorkflowViolation;
use App\Models\BudgetAdjustment;
use App\Models\BudgetAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Append-only changes to approved allotments: augmentation, reduction, and realignment between two
 * allotments of the same agency and fiscal year. Allotment rows are locked (in ID order, to avoid
 * deadlocks) so concurrent changes cannot push the available balance below zero.
 *
 * Each call carries an idempotency key: replaying the same request returns the original entry.
 */
class AdjustBudget
{
    public function __construct(private BudgetLedger $ledger) {}

    public function augment(User $actor, BudgetAllocation $allocation, string $amount, string $authorization, string $reason, string $idempotencyKey): BudgetAdjustment
    {
        return $this->single($actor, $allocation, 'augmentation', $amount, $authorization, $reason, $idempotencyKey);
    }

    public function reduce(User $actor, BudgetAllocation $allocation, string $amount, string $authorization, string $reason, string $idempotencyKey): BudgetAdjustment
    {
        return $this->single($actor, $allocation, 'reduction', $amount, $authorization, $reason, $idempotencyKey);
    }

    /** @return array{out: BudgetAdjustment, in: BudgetAdjustment} */
    public function realign(User $actor, BudgetAllocation $from, BudgetAllocation $to, string $amount, string $authorization, string $reason, string $idempotencyKey): array
    {
        $cents = $this->validate($actor, $amount, $authorization, $reason);
        if ($from->is($to)) {
            throw new WorkflowViolation('Realignment needs two different allotments.');
        }

        return DB::transaction(function () use ($actor, $from, $to, $cents, $authorization, $reason, $idempotencyKey) {
            if ($existing = $this->replay("{$idempotencyKey}:out", $from, 'realignment_out', $cents)) {
                return ['out' => $existing, 'in' => BudgetAdjustment::where('idempotency_key', "{$idempotencyKey}:in")->sole()];
            }

            $locked = BudgetAllocation::whereKey([$from->id, $to->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $locked[$from->id];
            $target = $locked[$to->id];
            $this->assertAdjustable($actor, $source);
            $this->assertAdjustable($actor, $target);
            if ($source->fiscal_year_id !== $target->fiscal_year_id) {
                throw new WorkflowViolation('Realignment is limited to allotments of the same fiscal year.');
            }
            $this->assertAvailable($source, $cents);

            $out = $this->insert($actor, $source, 'realignment_out', $cents, $authorization, $reason, "{$idempotencyKey}:out");
            $in = $this->insert($actor, $target, 'realignment_in', $cents, $authorization, $reason, "{$idempotencyKey}:in", $out->id);

            return ['out' => $out, 'in' => $in];
        });
    }

    private function single(User $actor, BudgetAllocation $allocation, string $type, string $amount, string $authorization, string $reason, string $key): BudgetAdjustment
    {
        $cents = $this->validate($actor, $amount, $authorization, $reason);

        return DB::transaction(function () use ($actor, $allocation, $type, $cents, $authorization, $reason, $key) {
            if ($existing = $this->replay($key, $allocation, $type, $cents)) {
                return $existing;
            }

            $locked = BudgetAllocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            $this->assertAdjustable($actor, $locked);
            if ($type === 'reduction') {
                $this->assertAvailable($locked, $cents);
            }

            return $this->insert($actor, $locked, $type, $cents, $authorization, $reason, $key);
        });
    }

    private function validate(User $actor, string $amount, string $authorization, string $reason): int
    {
        PlanGuards::requirePermission($actor, 'budget.allocate');
        $cents = Money::toCents($amount);
        if ($cents <= 0) {
            throw new WorkflowViolation('The adjustment amount must be positive.');
        }
        if (blank($authorization) || blank($reason)) {
            throw new WorkflowViolation('An authorization reference and a reason are required.');
        }

        return $cents;
    }

    private function replay(string $key, BudgetAllocation $allocation, string $type, int $cents): ?BudgetAdjustment
    {
        $existing = BudgetAdjustment::where('idempotency_key', $key)->first();
        if ($existing === null) {
            return null;
        }
        if ($existing->budget_allocation_id !== $allocation->id || $existing->adjustment_type !== $type || Money::toCents($existing->amount) !== $cents) {
            throw new WorkflowViolation('This idempotency key was already used for a different adjustment.');
        }

        return $existing;
    }

    private function assertAdjustable(User $actor, BudgetAllocation $allocation): void
    {
        if ($allocation->agency_id !== $actor->agency_id) {
            throw new WorkflowViolation('The allotment belongs to another agency.');
        }
        if (! $allocation->isApproved()) {
            throw new WorkflowViolation("Allotment {$allocation->reference_no} is not approved.");
        }
    }

    private function assertAvailable(BudgetAllocation $allocation, int $cents): void
    {
        $available = $this->ledger->balance($allocation)->available();
        if ($cents > $available) {
            throw new WorkflowViolation(sprintf('Only %s is available in %s.', Money::format(Money::fromCents($available)), $allocation->reference_no));
        }
    }

    private function insert(User $actor, BudgetAllocation $allocation, string $type, int $cents, string $authorization, string $reason, string $key, ?int $relatedId = null): BudgetAdjustment
    {
        return BudgetAdjustment::create([
            'budget_allocation_id' => $allocation->id,
            'adjustment_type' => $type,
            'amount' => Money::fromCents($cents),
            'authorization_reference' => $authorization,
            'reason' => $reason,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'related_adjustment_id' => $relatedId,
            'idempotency_key' => $key,
        ]);
    }
}
