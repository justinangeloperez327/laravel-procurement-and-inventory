<?php

namespace App\Policies;

class RegulatorySourcePolicy extends MasterDataPolicy
{
    protected ?string $managePermission = 'regulatory_rule.manage';
}
