<?php

namespace App\Policies;

class ItemPolicy extends MasterDataPolicy
{
    protected ?string $managePermission = 'master_data.manage';
}
