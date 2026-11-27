<?php

use App\Domain\Identity\RoleCatalog;

it('references only declared permissions', function () {
    foreach (RoleCatalog::ROLES as $code => $role) {
        foreach ($role['permissions'] as $permission) {
            expect(array_key_exists($permission, RoleCatalog::PERMISSIONS))->toBeTrue("{$code} uses undeclared {$permission}");
        }
    }
});

it('gives the technical administrator no statutory permissions', function () {
    expect(RoleCatalog::ROLES['system_administrator']['permissions'])->toBe([]);
});

it('gives read-only roles no mutating permissions', function () {
    foreach (RoleCatalog::ROLES as $role) {
        if ($role['read_only'] ?? false) {
            expect(array_diff($role['permissions'], ['audit.read']))->toBe([]);
        }
    }
});

it('segregates BAC recommendation from HoPE approval', function (string $bacRole) {
    expect(RoleCatalog::conflicts('hope', $bacRole))->toBeTrue()
        ->and(RoleCatalog::conflicts($bacRole, 'hope'))->toBeTrue();
})->with(['bac_chair', 'bac_member', 'bac_secretariat', 'twg_member']);

it('separates receiving from inspection and invoicing from payment', function () {
    expect(RoleCatalog::conflicts('receiving_officer', 'inspection_officer'))->toBeTrue()
        ->and(RoleCatalog::conflicts('accounting_officer', 'cashier'))->toBeTrue()
        ->and(RoleCatalog::conflicts('end_user', 'department_head'))->toBeFalse();
});

it('keeps the auditor independent of transactional roles', function () {
    expect(RoleCatalog::conflicts('auditor', 'budget_officer'))->toBeTrue()
        ->and(RoleCatalog::conflicts('end_user', 'auditor'))->toBeTrue()
        ->and(RoleCatalog::conflicts('auditor', 'observer'))->toBeFalse();
});

it('finds the roles that grant a permission', function () {
    expect(RoleCatalog::rolesGranting('award.approve'))->toBe(['hope'])
        ->and(RoleCatalog::rolesGranting('bid.evaluate'))->toEqualCanonicalizing(['bac_chair', 'bac_member', 'twg_member']);
});
