<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Changes are audited explicitly by the identity actions (role.granted, delegation.revoked, ...). */
class RoleAssignment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query, ?CarbonInterface $at = null): void
    {
        $at ??= now();
        $query->whereNull('revoked_at')
            ->where('granted_at', '<=', $at)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $at));
    }

    public function isActive(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->revoked_at === null
            && $this->granted_at->lte($at)
            && ($this->expires_at === null || $this->expires_at->gt($at));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
