<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name', 'description', 'is_read_only'];

    protected function casts(): array
    {
        return ['is_read_only' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }
}
