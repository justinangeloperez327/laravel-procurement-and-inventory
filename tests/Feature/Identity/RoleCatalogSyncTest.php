<?php

use App\Domain\Identity\RoleCatalog;
use App\Enums\ProcurementMode;
use App\Models\Permission;
use App\Models\ProcurementModeDefinition;
use App\Models\Role;

it('seeds the catalog idempotently', function () {
    seedRoles();
    seedRoles();

    expect(Role::count())->toBe(count(RoleCatalog::ROLES))
        ->and(Permission::count())->toBe(count(RoleCatalog::PERMISSIONS))
        ->and(role('hope')->permissions->pluck('code')->all())->toEqualCanonicalizing(['app.approve', 'award.approve']);
});

it('is exposed as an artisan command and via the database seeder', function () {
    $this->artisan('gpims:sync-roles')->assertSuccessful();
    $this->seed();

    expect(Role::where('code', 'auditor')->value('is_read_only'))->toBeTrue();
});

it('seeds one procurement mode row per RA 12009 mode', function () {
    $this->seed();
    $this->seed();

    expect(ProcurementModeDefinition::count())->toBe(count(ProcurementMode::cases()));
});
