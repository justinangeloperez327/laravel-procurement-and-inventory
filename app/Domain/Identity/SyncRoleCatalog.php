<?php

namespace App\Domain\Identity;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

/** Idempotently creates the catalog permissions and roles, and syncs role permissions. */
class SyncRoleCatalog
{
    public function __invoke(): void
    {
        DB::transaction(function () {
            $ids = [];
            foreach (RoleCatalog::PERMISSIONS as $code => $description) {
                $ids[$code] = Permission::updateOrCreate(['code' => $code], ['description' => $description])->id;
            }

            foreach (RoleCatalog::ROLES as $code => $definition) {
                $role = Role::firstOrCreate(['code' => $code], [
                    'name' => $definition['name'],
                    'is_read_only' => $definition['read_only'] ?? false,
                ]);
                $result = $role->permissions()->sync(array_map(fn ($p) => $ids[$p], $definition['permissions']));

                if (array_filter($result)) {
                    AuditLog::record('role.permissions_synced', $role, null, null, $result);
                }
            }
        });
    }
}
