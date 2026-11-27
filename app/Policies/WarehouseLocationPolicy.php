<?php

namespace App\Policies;

class WarehouseLocationPolicy extends MasterDataPolicy
{
    protected ?string $managePermission = 'master_data.manage';
}
