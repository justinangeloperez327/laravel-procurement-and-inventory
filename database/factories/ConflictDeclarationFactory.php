<?php

namespace Database\Factories;

use App\Models\ConflictDeclaration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConflictDeclaration> */
class ConflictDeclarationFactory extends Factory
{
    protected $model = ConflictDeclaration::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'procurement_project_id' => null,
            'supplier_id' => null,
            'declared_at' => now(),
            'has_conflict' => false,
            'nature' => null,
            'action_taken' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }
}
