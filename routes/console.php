<?php

use App\Domain\Approvals\DefaultWorkflows;
use App\Domain\Identity\SyncRoleCatalog;
use App\Models\Agency;
use Illuminate\Support\Facades\Artisan;

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
