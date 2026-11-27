<?php

namespace App\Policies;

use App\Domain\Identity\Authorizer;
use App\Domain\Requests\PurchaseRequestAccess;
use App\Models\PurchaseRequest;
use App\Models\User;

class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && PurchaseRequestAccess::departmentIds($user) !== [];
    }

    public function view(User $user, PurchaseRequest $pr): bool
    {
        return $user->is_active && PurchaseRequestAccess::canSeeDepartment($user, $pr->agency_id, $pr->department_id);
    }

    public function create(User $user): bool
    {
        return $user->is_active && app(Authorizer::class)->holdsAnywhere($user, 'purchase_request.create');
    }

    public function update(User $user, PurchaseRequest $pr): bool
    {
        return $pr->isEditable() && $user->agency_id === $pr->agency_id && $user->hasPermission('purchase_request.create', $pr->department);
    }

    public function delete(User $user, PurchaseRequest $pr): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
