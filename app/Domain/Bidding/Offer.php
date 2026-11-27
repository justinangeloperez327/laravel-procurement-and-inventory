<?php

namespace App\Domain\Bidding;

use App\Models\Bid;
use App\Models\Quotation;
use App\Models\Supplier;

/** A bid or a quotation, as ranked in evaluation. */
final class Offer
{
    /** @param  array<int, array{passed: ?bool, score: ?string}>  $results  criterion id => outcome */
    public function __construct(
        public readonly Bid|Quotation $model,
        public readonly array $results = [],
        public readonly bool $complete = false,
        public readonly bool $passed = false,
        public readonly ?float $weightedScore = null,
    ) {}

    public function supplier(): Supplier
    {
        return $this->model->supplier;
    }

    public function amount(): string
    {
        return (string) ($this->model instanceof Bid ? $this->model->bid_amount : $this->model->total_amount);
    }

    public function lotId(): ?int
    {
        return self::lotOf($this->model);
    }

    public static function lotOf(Bid|Quotation $model): ?int
    {
        return $model instanceof Bid ? $model->procurement_lot_id : $model->rfq->procurement_lot_id;
    }

    public function key(): string
    {
        return ($this->model instanceof Bid ? 'bid:' : 'quotation:').$this->model->id;
    }
}
