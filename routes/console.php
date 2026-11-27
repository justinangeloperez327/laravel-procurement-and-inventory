<?php

use App\Domain\Approvals\DefaultWorkflows;
use App\Domain\Identity\SyncRoleCatalog;
use App\Domain\Suppliers\SupplierComplianceSweep;
use App\Models\Agency;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('gpims:sync-roles', function (SyncRoleCatalog $sync) {
    $sync();
    $this->info('Role and permission catalog synchronized.');
})->purpose('Create or update the default roles and permissions from App\Domain\Identity\RoleCatalog');

Artisan::command('gpims:install-default-workflows {agency : Agency code}', function (string $agency) {
    $model = Agency::where('code', $agency)->first();
    if ($model === null) {
        $this->error("No agency with code {$agency}.");

        return 1;
    }
    $created = DefaultWorkflows::install($model);
    $this->info($created ? 'Created: '.implode(', ', $created) : 'All default workflows already exist.');
    $this->warn('Confirm these routes against the agency office orders before production use.');

    return 0;
})->purpose('Create starter approval workflows (market scoping, PPMP, APP, purchase request) for an agency');

Artisan::command('gpims:supplier-compliance-sweep', function (SupplierComplianceSweep $sweep) {
    $counts = $sweep->run();
    $this->info("Status changes: {$counts['status_changes']}; expired documents: {$counts['expired']}; expiring soon: {$counts['expiring']}.");
})->purpose('Align supplier status with sanctions and flag expired or expiring eligibility documents');

Schedule::command('gpims:supplier-compliance-sweep')->dailyAt('00:30')->onOneServer()->withoutOverlapping();
