<?php

namespace App\Policies;

use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class FiscalYearPolicy extends MasterDataPolicy
{
    /** A closed fiscal year is final. */
    public function update(User $user, Model $record): bool
    {
        return $record instanceof FiscalYear && ! $record->isClosed() && parent::update($user, $record);
    }
}
