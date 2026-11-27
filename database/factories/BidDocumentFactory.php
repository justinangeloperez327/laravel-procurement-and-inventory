<?php

namespace Database\Factories;

use App\Models\Bid;
use App\Models\BidDocument;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BidDocument> */
class BidDocumentFactory extends Factory
{
    protected $model = BidDocument::class;

    public function definition(): array
    {
        return [
            'bid_id' => Bid::factory(),
            'document_id' => Document::factory(),
            'envelope' => 'eligibility_technical',
            'is_sealed' => true,
        ];
    }
}
