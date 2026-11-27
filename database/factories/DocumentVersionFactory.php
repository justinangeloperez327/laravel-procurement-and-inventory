<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentVersion> */
class DocumentVersionFactory extends Factory
{
    protected $model = DocumentVersion::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'version' => 1,
            'storage_disk' => 'local',
            'storage_key' => 'documents/'.fake()->uuid(),
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1000, 5000000),
            'sha256' => hash('sha256', fake()->uuid()),
            'author_id' => User::factory(),
            'signer_id' => null,
            'signed_at' => null,
            'transaction_reference' => null,
        ];
    }
}
