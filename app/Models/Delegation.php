<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Changes are audited explicitly by the identity actions (role.granted, delegation.revoked, ...). */
class Delegation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query, ?CarbonInterface $at = null): void
    {
        $at ??= now();
        $query->whereNull('revoked_at')->where('starts_at', '<=', $at)->where('ends_at', '>', $at);
    }

    public function isActive(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->revoked_at === null && $this->starts_at->lte($at) && $this->ends_at->gt($at);
    }

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
