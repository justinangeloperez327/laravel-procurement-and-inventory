<?php

namespace Database\Factories;

use App\Models\RegulatorySource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RegulatorySource> */
class RegulatorySourceFactory extends Factory
{
    protected $model = RegulatorySource::class;

    public function definition(): array
    {
        return [
            'code' => 'SRC-'.fake()->unique()->numerify('#####'),
            'title' => fake()->sentence(4),
            'issuer' => 'GPPB',
            'reference' => 'RA 12009',
            'url' => null,
            'issued_on' => null,
            'effective_from' => null,
            'notes' => null,
        ];
    }
}
