<?php

namespace Database\Factories;

use App\Models\BacCommittee;
use App\Models\BacMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BacMember> */
class BacMemberFactory extends Factory
{
    protected $model = BacMember::class;

    public function definition(): array
    {
        return [
            'bac_committee_id' => BacCommittee::factory(),
            'user_id' => User::factory(),
            'member_role' => 'member',
            'designation_reference' => null,
            'starts_on' => now(),
            'ends_on' => null,
        ];
    }
}
