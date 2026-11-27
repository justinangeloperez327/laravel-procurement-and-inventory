<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Object/account code (e.g. UACS). Validate against current DBM/COA lists. */
class AccountCode extends Model
{
    use Auditable, HasFactory;

    protected $table = 'account_codes';

    protected $fillable = [
        'code',
        'name',
        'classification',
        'parent_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AccountCode::class, 'parent_id');
    }
}
