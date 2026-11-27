<?php

namespace App\Policies;

class ItemCategoryPolicy extends MasterDataPolicy
{
    protected ?string $managePermission = 'master_data.manage';
}
