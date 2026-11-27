<?php

namespace Database\Seeders;

use App\Domain\Identity\SyncRoleCatalog;
use App\Enums\ProcurementMode;
use App\Models\ProcurementModeDefinition;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Seeds reference data only. No users or default credentials are ever seeded. */
    public function run(): void
    {
        app(SyncRoleCatalog::class)();

        foreach (ProcurementMode::cases() as $mode) {
            ProcurementModeDefinition::updateOrCreate(
                ['code' => $mode],
                ['name' => $mode->label(), 'legal_reference' => 'RA 12009, Article IV'],
            );
        }
    }
}
