<?php

namespace App\Domain\Suppliers;

use App\Domain\Shared\WorkflowViolation;
use App\Models\Supplier;
use App\Models\User;

final class SupplierGuard
{
    public static function require(User $actor, string $permission, ?Supplier $supplier = null): void
    {
        if (! $actor->hasPermission($permission)) {
            throw new WorkflowViolation("You do not hold {$permission}.");
        }
        if ($supplier !== null && $supplier->agency_id !== $actor->agency_id) {
            throw new WorkflowViolation('The supplier belongs to another agency.');
        }
    }
}
