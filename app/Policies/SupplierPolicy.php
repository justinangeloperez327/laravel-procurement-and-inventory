<?php

namespace App\Policies;

use App\Domain\Identity\Authorizer;
use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    /** Who works with the supplier registry. */
    public const READERS = ['supplier.manage', 'supplier.verify', 'supplier.sanction', 'compliance.review', 'rfq.manage', 'bid.evaluate', 'audit.read'];

    public function viewAny(User $user): bool
    {
        if ($user->is_admin && $user->is_active) {
            return true;
        }
        $authorizer = app(Authorizer::class);

        return collect(self::READERS)->contains(fn (string $p) => $authorizer->holdsAnywhere($user, $p));
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->viewAny($user) && (($user->is_admin && $user->is_active) || $user->agency_id === $supplier->agency_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('supplier.manage');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->agency_id === $supplier->agency_id && $user->hasPermission('supplier.manage');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
