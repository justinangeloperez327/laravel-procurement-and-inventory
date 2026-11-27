<?php

namespace Database\Factories;

use App\Models\BacCommittee;
use App\Models\TwgMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TwgMember> */
class TwgMemberFactory extends Factory
{
    protected $model = TwgMember::class;

    public function definition(): array
    {
        return [
            'bac_committee_id' => BacCommittee::factory(),
            'user_id' => User::factory(),
            'expertise' => null,
            'starts_on' => now(),
            'ends_on' => null,
        ];
    }
}
