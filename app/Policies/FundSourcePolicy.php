<?php

namespace App\Policies;

class FundSourcePolicy extends MasterDataPolicy
{
    protected ?string $managePermission = 'budget.master_data';
}
