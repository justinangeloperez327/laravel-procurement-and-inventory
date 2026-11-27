<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetTransfer> */
class AssetTransferFactory extends Factory
{
    protected $model = AssetTransfer::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'from_custodian_id' => User::factory(),
            'to_custodian_id' => User::factory(),
            'ptr_reference' => 'PTR-'.fake()->numerify('######'),
            'reason' => null,
            'approved_by' => null,
            'transferred_at' => null,
        ];
    }
}
