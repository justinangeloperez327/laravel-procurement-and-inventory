<?php

namespace App\Policies;

class WarehousePolicy extends MasterDataPolicy
{
    protected ?string $managePermission = 'master_data.manage';
}
