<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

/**
 * For resources whose records belong to an agency: non-administrators see and create records of
 * their own agency only. Policies enforce the same rule on each record.
 */
trait ScopedToUserAgency
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();

        if ($user instanceof User && ! ($user->is_admin && $user->is_active)) {
            $query->where($query->qualifyColumn('agency_id'), $user->agency_id);
        }

        return $query;
    }

    protected static function agencySelect(): Select
    {
        return Select::make('agency_id')
            ->label('Agency')
            ->relationship('agency', 'name', function (Builder $query) {
                $user = Filament::auth()->user();
                if ($user instanceof User && ! ($user->is_admin && $user->is_active)) {
                    $query->whereKey($user->agency_id);
                }
            })
            ->default(fn () => Filament::auth()->user()?->agency_id)
            ->required()
            ->live()
            ->disabledOn('edit');
    }
}
