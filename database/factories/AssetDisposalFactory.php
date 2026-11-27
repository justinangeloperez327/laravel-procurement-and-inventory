<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetDisposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetDisposal> */
class AssetDisposalFactory extends Factory
{
    protected $model = AssetDisposal::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'disposal_method' => 'sale',
            'iirup_reference' => null,
            'reason' => fake()->sentence(),
            'approved_by' => null,
            'approved_at' => null,
            'disposed_on' => null,
            'proceeds' => null,
        ];
    }
}
