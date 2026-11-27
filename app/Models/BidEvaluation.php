<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidEvaluation extends Model
{
    use Auditable, HasFactory;

    protected $table = 'bid_evaluations';

    protected $fillable = [
        'bid_id',
        'quotation_id',
        'evaluation_criterion_id',
        'evaluator_id',
        'result',
        'score',
        'remarks',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:4',
            'evaluated_at' => 'datetime',
        ];
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'bid_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function evaluationCriterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriterion::class, 'evaluation_criterion_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
