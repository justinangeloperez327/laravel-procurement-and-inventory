<?php

namespace App\Policies;

class UnitOfMeasurePolicy extends MasterDataPolicy
{
    protected ?string $managePermission = 'master_data.manage';
}
