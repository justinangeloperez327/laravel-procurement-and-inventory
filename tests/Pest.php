<?php

use App\Domain\Identity\GrantRole;
use App\Domain\Identity\SyncRoleCatalog;
use App\Models\Agency;
use App\Models\Department;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');
pest()->use(RefreshDatabase::class)->in('Feature');

function seedRoles(): void
{
    app(SyncRoleCatalog::class)();
}

function role(string $code): Role
{
    return Role::where('code', $code)->firstOrFail();
}

function adminFor(Agency $agency): User
{
    return User::factory()->forAgency($agency)->admin()->create();
}

/** Grants a role through the real domain action, using a separate administrator as grantor. */
function grant(User $user, string $roleCode, ?Department $department = null, ?DateTimeInterface $expiresAt = null): RoleAssignment
{
    $admin = User::factory()->forAgency($user->agency)->admin()->create();

    return app(GrantRole::class)($admin, $user, role($roleCode), $department, $expiresAt ? CarbonImmutable::instance($expiresAt) : null, 'Test designation');
}
