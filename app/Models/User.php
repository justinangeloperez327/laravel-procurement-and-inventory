<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Identity\Authorizer;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * is_admin marks a technical system administrator. It grants access to identity administration
 * but never statutory approval authority; that comes only from role assignments and delegations.
 * is_admin is deliberately not mass assignable.
 */
#[Fillable(['name', 'email', 'password', 'agency_id', 'department_id', 'employee_number', 'position', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    protected $attributes = ['is_admin' => false, 'is_active' => true];

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->is_admin) {
            return true;
        }

        return $this->exists && $this->roleAssignments()->active()->exists();
    }

    public function hasPermission(string $permission, ?Department $department = null): bool
    {
        return app(Authorizer::class)->allows($this, $permission, $department);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function delegationsReceived(): HasMany
    {
        return $this->hasMany(Delegation::class, 'delegate_id');
    }

    public function delegationsGiven(): HasMany
    {
        return $this->hasMany(Delegation::class, 'delegator_id');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
