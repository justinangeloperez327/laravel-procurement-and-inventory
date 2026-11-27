<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\AssetClass;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemCategory extends Model
{
    use Auditable, HasFactory;

    protected $table = 'item_categories';

    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'default_asset_class',
        'account_code_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_asset_class' => AssetClass::class,
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'parent_id');
    }

    public function accountCode(): BelongsTo
    {
        return $this->belongsTo(AccountCode::class, 'account_code_id');
    }
}
