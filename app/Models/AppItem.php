<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\ProcurementMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppItem extends Model
{
    use Auditable, HasFactory;

    protected $table = 'app_items';

    protected $fillable = [
        'app_version_id',
        'code',
        'description',
        'item_category_id',
        'procurement_mode',
        'estimated_budget',
        'fund_source_id',
        'advertisement_on',
        'opening_on',
        'award_on',
        'contract_signing_on',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'procurement_mode' => ProcurementMode::class,
            'estimated_budget' => 'decimal:2',
            'advertisement_on' => 'date',
            'opening_on' => 'date',
            'award_on' => 'date',
            'contract_signing_on' => 'date',
        ];
    }

    public function appVersion(): BelongsTo
    {
        return $this->belongsTo(AppVersion::class, 'app_version_id');
    }

    public function itemCategory(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function fundSource(): BelongsTo
    {
        return $this->belongsTo(FundSource::class, 'fund_source_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(AppItemSource::class, 'app_item_id');
    }
}
