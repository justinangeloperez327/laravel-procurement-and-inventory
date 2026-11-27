<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacMember extends Model
{
    use Auditable, HasFactory;

    protected $table = 'bac_members';

    protected $fillable = [
        'bac_committee_id',
        'user_id',
        'member_role',
        'designation_reference',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function bacCommittee(): BelongsTo
    {
        return $this->belongsTo(BacCommittee::class, 'bac_committee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
