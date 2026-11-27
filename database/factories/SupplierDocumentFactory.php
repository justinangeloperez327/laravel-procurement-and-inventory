<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupplierDocument> */
class SupplierDocumentFactory extends Factory
{
    protected $model = SupplierDocument::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'document_id' => null,
            'document_type' => 'tax_clearance',
            'reference_no' => null,
            'issued_on' => null,
            'expires_on' => now()->addYear(),
            'recorded_by' => User::factory(),
            'verified_by' => null,
            'verified_at' => null,
        ];
    }
}
