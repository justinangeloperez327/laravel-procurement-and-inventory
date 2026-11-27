<?php

namespace Database\Seeders;

use App\Domain\Identity\SyncRoleCatalog;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Seeds reference data only. No users or default credentials are ever seeded. */
    public function run(): void
    {
        app(SyncRoleCatalog::class)();
    }
}
