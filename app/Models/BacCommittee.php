<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BacCommittee extends Model
{
    use Auditable, HasFactory;

    protected $table = 'bac_committees';

    protected $fillable = [
        'agency_id',
        'name',
        'committee_type',
        'constituted_on',
        'office_order_reference',
        'dissolved_on',
    ];

    protected function casts(): array
    {
        return [
            'constituted_on' => 'date',
            'dissolved_on' => 'date',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(BacMember::class, 'bac_committee_id');
    }

    public function twgMembers(): HasMany
    {
        return $this->hasMany(TwgMember::class, 'bac_committee_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'bac_committee_id');
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(BacResolution::class, 'bac_committee_id');
    }
}
