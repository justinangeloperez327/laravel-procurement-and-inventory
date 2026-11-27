<?php

namespace Database\Factories;

use App\Models\Bid;
use App\Models\BidEvaluation;
use App\Models\EvaluationCriterion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BidEvaluation> */
class BidEvaluationFactory extends Factory
{
    protected $model = BidEvaluation::class;

    public function definition(): array
    {
        return [
            'bid_id' => Bid::factory(),
            'quotation_id' => null,
            'evaluation_criterion_id' => EvaluationCriterion::factory(),
            'evaluator_id' => User::factory(),
            'result' => 'pass',
            'score' => null,
            'remarks' => null,
            'evaluated_at' => now(),
        ];
    }
}
