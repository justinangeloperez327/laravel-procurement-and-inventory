<?php

use App\Domain\Identity\SyncRoleCatalog;
use Illuminate\Support\Facades\Artisan;

Artisan::command('gpims:sync-roles', function (SyncRoleCatalog $sync) {
    $sync();
    $this->info('Role and permission catalog synchronized.');
})->purpose('Create or update the default roles and permissions from App\Domain\Identity\RoleCatalog');
