<?php

namespace Database\Factories;

use App\Enums\DocumentClassification;
use App\Models\Agency;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'documentable_type' => null,
            'documentable_id' => null,
            'document_type' => 'PR',
            'title' => fake()->sentence(4),
            'classification' => DocumentClassification::Internal,
            'retention_policy' => null,
            'created_by' => fn (array $attributes) => User::factory()->state(['agency_id' => $attributes['agency_id']]),
        ];
    }
}
