<?php

namespace App\Filament\Concerns;

use App\Domain\Identity\Authorizer;
use App\Domain\Planning\PlanningAccess;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

/** Department-plan list scoping and option lists for planning screens. */
trait PlanningQueries
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }
        if (! ($user->is_admin && $user->is_active)) {
            $query->where($query->qualifyColumn('agency_id'), $user->agency_id);
        }
        $departments = PlanningAccess::departmentIds($user);

        return $departments === null ? $query : $query->whereIn($query->qualifyColumn('department_id'), $departments);
    }

    /** @return array<int, string> Departments where the user may prepare plans. */
    protected static function preparableDepartments(): array
    {
        $user = Filament::auth()->user();
        $scope = app(Authorizer::class)->departmentScope($user, 'ppmp.create');

        return Department::query()
            ->where('agency_id', $user->agency_id)
            ->where('is_active', true)
            ->when($scope !== null, fn (Builder $q) => $q->whereKey($scope))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> */
    protected static function openFiscalYears(): array
    {
        return FiscalYear::query()
            ->where('agency_id', Filament::auth()->user()->agency_id)
            ->where('status', FiscalYear::STATUS_OPEN)
            ->orderByDesc('year')
            ->pluck('year', 'id')
            ->all();
    }
}
