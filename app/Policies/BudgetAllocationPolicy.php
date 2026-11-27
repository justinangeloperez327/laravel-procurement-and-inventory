<?php

namespace App\Policies;

use App\Domain\Identity\Authorizer;
use App\Models\BudgetAllocation;
use App\Models\User;

class BudgetAllocationPolicy
{
    private const READERS = ['budget.allocate', 'budget.certify', 'budget.reserve', 'app.approve', 'audit.read'];

    public function viewAny(User $user): bool
    {
        if ($user->is_admin && $user->is_active) {
            return true;
        }
        $authorizer = app(Authorizer::class);

        return collect(self::READERS)->contains(fn (string $p) => $authorizer->holdsAnywhere($user, $p));
    }

    public function view(User $user, BudgetAllocation $allocation): bool
    {
        return $this->viewAny($user) && (($user->is_admin && $user->is_active) || $user->agency_id === $allocation->agency_id);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasPermission('budget.allocate');
    }

    public function update(User $user, BudgetAllocation $allocation): bool
    {
        return $allocation->isEditable() && $user->agency_id === $allocation->agency_id && $user->hasPermission('budget.allocate');
    }

    public function approve(User $user, BudgetAllocation $allocation): bool
    {
        return $allocation->isEditable() && $allocation->recorded_by !== $user->id
            && $user->agency_id === $allocation->agency_id && $user->hasPermission('budget.allocate');
    }

    public function adjust(User $user, BudgetAllocation $allocation): bool
    {
        return $allocation->isApproved() && $user->agency_id === $allocation->agency_id && $user->hasPermission('budget.allocate');
    }

    public function delete(User $user, BudgetAllocation $allocation): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
