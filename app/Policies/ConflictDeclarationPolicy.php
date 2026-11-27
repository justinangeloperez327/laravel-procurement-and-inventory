<?php

namespace App\Policies;

use App\Models\ConflictDeclaration;
use App\Models\User;

/** Everyone sees and makes their own declarations; reviewers and auditors see all in their agency. */
class ConflictDeclarationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->agency_id !== null;
    }

    public function view(User $user, ConflictDeclaration $declaration): bool
    {
        return $declaration->user_id === $user->id || (self::seesAll($user) && $declaration->user->agency_id === $user->agency_id);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->agency_id !== null;
    }

    public function review(User $user, ConflictDeclaration $declaration): bool
    {
        return $declaration->reviewed_at === null && $declaration->user_id !== $user->id
            && $declaration->user->agency_id === $user->agency_id && $user->hasPermission('compliance.review');
    }

    public function update(User $user, ConflictDeclaration $declaration): bool
    {
        return false;
    }

    public function delete(User $user, ConflictDeclaration $declaration): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public static function seesAll(User $user): bool
    {
        return $user->hasPermission('compliance.review') || $user->hasPermission('audit.read');
    }
}
