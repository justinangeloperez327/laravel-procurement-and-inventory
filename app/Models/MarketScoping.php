<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketScoping extends Model
{
    use Auditable, HasFactory;

    protected $table = 'market_scopings';

    protected $fillable = [
        'agency_id',
        'department_id',
        'fiscal_year_id',
        'reference_no',
        'title',
        'description',
        'conducted_by',
        'conducted_on',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'conducted_on' => 'date',
            'status' => PlanStatus::class,
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function conductedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketScopingItem::class, 'market_scoping_id');
    }
}
