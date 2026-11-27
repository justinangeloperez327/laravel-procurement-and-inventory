<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Enums\SupplierStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use Auditable, HasFactory;

    protected $table = 'suppliers';

    protected $fillable = [
        'agency_id',
        'name',
        'trade_name',
        'philgeps_registration_no',
        'tin',
        'business_type',
        'address',
        'email',
        'phone',
        'status',
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupplierStatus::class,
            'registered_at' => 'date',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class, 'supplier_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class, 'supplier_id');
    }

    public function beneficialOwners(): HasMany
    {
        return $this->hasMany(SupplierBeneficialOwner::class, 'supplier_id');
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(SupplierSanction::class, 'supplier_id');
    }
}
