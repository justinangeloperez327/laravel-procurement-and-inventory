<?php

namespace App\Policies;

use App\Enums\RuleStatus;
use App\Models\RegulatoryRuleVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Drafts are maintained by rule managers; approval and retirement go through the legal reviewer actions. */
class RegulatoryRuleVersionPolicy extends MasterDataPolicy
{
    protected ?string $managePermission = 'regulatory_rule.manage';

    public function viewAny(User $user): bool
    {
        return parent::viewAny($user) || $user->hasPermission('regulatory_rule.approve');
    }

    public function update(User $user, Model $record): bool
    {
        return $record instanceof RegulatoryRuleVersion && $record->status === RuleStatus::Draft && parent::update($user, $record);
    }

    public function approve(User $user, RegulatoryRuleVersion $version): bool
    {
        return $version->status === RuleStatus::Draft && $version->prepared_by !== $user->id && $user->hasPermission('regulatory_rule.approve');
    }

    public function retire(User $user, RegulatoryRuleVersion $version): bool
    {
        return $version->status === RuleStatus::Approved && $user->hasPermission('regulatory_rule.approve');
    }
}
