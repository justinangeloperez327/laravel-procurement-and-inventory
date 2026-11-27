<?php

namespace App\Policies;

class AccountCodePolicy extends MasterDataPolicy
{
    protected ?string $managePermission = 'budget.master_data';
}
