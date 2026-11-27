<?php

namespace App\Domain\Bidding;

use App\Domain\Shared\Money;
use App\Domain\Shared\TransitionStatus;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\ProcurementCategory;
use App\Enums\ProcurementProjectStatus;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Bidding;
use App\Models\BidEvaluation;
use App\Models\EvaluationCriterion;
use App\Models\ProcurementProject;
use App\Models\Quotation;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Evaluation of opened bids and on-time quotations against the published criteria (L-08).
 *
 * - Each evaluator records one result per offer and criterion; results are immutable.
 * - Evaluators must have declared and must not have a conflict with the project or the supplier
 *   (docs/16 "Conflicted evaluator").
 * - A pass/fail criterion is met when most of the evaluators who assessed it passed it; rated criteria
 *   are averaged and weighted.
 * - Ranking: goods and infrastructure by lowest amount among passing offers; consulting services by
 *   highest weighted score.
 */
class Evaluation
{
    public function __construct(private TransitionStatus $transition) {}

    public function evaluate(User $evaluator, Bid|Quotation $offer, EvaluationCriterion $criterion, string $result, ?string $score = null, ?string $remarks = null): BidEvaluation
    {
        $project = self::projectOf($offer);
        BiddingGuard::require($evaluator, 'bid.evaluate', $project);
        BiddingGuard::requireStatus($project, [ProcurementProjectStatus::Evaluation]);
        BiddingGuard::requireIndependent($evaluator, $project, $offer->supplier);

        if ($criterion->procurement_project_id !== $project->id || $criterion->locked_at === null) {
            throw new WorkflowViolation('Evaluate only against the published criteria of this project.');
        }
        if ($offer instanceof Bid && ($offer->opened_at === null || $offer->is_late || $offer->withdrawn_at !== null)) {
            throw new WorkflowViolation('Only opened, on-time bids can be evaluated.');
        }
        if ($offer instanceof Quotation && $offer->is_late) {
            throw new WorkflowViolation('Late quotations are not evaluated.');
        }
        if ($criterion->criterion_type === 'pass_fail' && ! in_array($result, ['pass', 'fail'], true)) {
            throw new WorkflowViolation('Pass/fail criteria are rated pass or fail.');
        }
        if ($criterion->criterion_type === 'rated' && (! is_numeric($score) || (float) $score < 0 || (float) $score > 100)) {
            throw new WorkflowViolation('Rated criteria need a score from 0 to 100.');
        }

        $evaluation = BidEvaluation::create([
            'bid_id' => $offer instanceof Bid ? $offer->id : null,
            'quotation_id' => $offer instanceof Quotation ? $offer->id : null,
            'evaluation_criterion_id' => $criterion->id,
            'evaluator_id' => $evaluator->id,
            'result' => $criterion->criterion_type === 'rated' ? 'score' : $result,
            'score' => $criterion->criterion_type === 'rated' ? $score : null,
            'remarks' => $remarks,
            'evaluated_at' => now(),
        ]);
        AuditLog::record('offer.evaluated', $evaluation, null, ['criterion' => $criterion->criterion, 'result' => $evaluation->result]);

        return $evaluation;
    }

    /** @return Collection<int, Offer> Offers of the project's active solicitations, ranked best first. */
    public function rank(ProcurementProject $project): Collection
    {
        $criteria = $project->criteria()->whereNotNull('locked_at')->get();
        $offers = self::offers($project);
        $consulting = $project->category === ProcurementCategory::ConsultingServices;

        $ranked = $offers->map(function (Bid|Quotation $offer) use ($criteria) {
            $evaluations = BidEvaluation::where($offer instanceof Bid ? 'bid_id' : 'quotation_id', $offer->id)->get()->groupBy('evaluation_criterion_id');
            $lotId = Offer::lotOf($offer);
            $applicable = $criteria->filter(fn ($c) => $c->procurement_lot_id === null || $c->procurement_lot_id === $lotId);
            $results = [];
            $complete = true;
            $passed = true;
            $weighted = 0.0;
            $weights = 0.0;
            foreach ($applicable as $criterion) {
                $rows = $evaluations->get($criterion->id, collect());
                if ($rows->isEmpty()) {
                    $complete = false;
                    $results[$criterion->id] = ['passed' => null, 'score' => null];

                    continue;
                }
                if ($criterion->criterion_type === 'pass_fail') {
                    $pass = $rows->where('result', 'pass')->count() > $rows->where('result', 'fail')->count();
                    $passed = $passed && $pass;
                    $results[$criterion->id] = ['passed' => $pass, 'score' => null];
                } else {
                    $average = $rows->avg(fn ($r) => (float) $r->score);
                    $weighted += $average * (float) $criterion->weight;
                    $weights += (float) $criterion->weight;
                    $results[$criterion->id] = ['passed' => null, 'score' => number_format($average, 2, '.', '')];
                }
            }

            return new Offer($offer, $results, $complete, $complete && $passed, $weights > 0 ? round($weighted / $weights, 4) : null);
        });

        return $ranked->sort(function (Offer $a, Offer $b) use ($consulting) {
            if ($a->passed !== $b->passed) {
                return $a->passed ? -1 : 1;
            }
            if ($consulting) {
                return ($b->weightedScore ?? 0) <=> ($a->weightedScore ?? 0);
            }
            $byAmount = Money::toCents($a->amount()) <=> Money::toCents($b->amount());

            return $byAmount !== 0 ? $byAmount : $a->model->getAttribute($a->model instanceof Bid ? 'submitted_at' : 'received_at')
                <=> $b->model->getAttribute($b->model instanceof Bid ? 'submitted_at' : 'received_at');
        })->values();
    }

    /** Closes evaluation once every offer is fully evaluated and moves to post-qualification. */
    public function finish(User $actor, ProcurementProject $project): Collection
    {
        BiddingGuard::require($actor, 'bac.recommend', $project);
        BiddingGuard::requireStatus($project, [ProcurementProjectStatus::Evaluation]);

        return DB::transaction(function () use ($actor, $project) {
            $ranked = $this->rank($project);
            if ($ranked->isEmpty()) {
                throw new WorkflowViolation('There are no offers to evaluate. Declare a failure of procurement instead.');
            }
            if ($ranked->contains(fn (Offer $o) => ! $o->complete)) {
                throw new WorkflowViolation('Every offer must be evaluated against every criterion first.');
            }
            if (! $ranked->contains(fn (Offer $o) => $o->passed)) {
                throw new WorkflowViolation('No offer passed evaluation. Declare a failure of procurement instead.');
            }

            foreach ($ranked as $offer) {
                $status = $offer->model instanceof Bid ? ($offer->passed ? 'passed' : 'failed') : ($offer->passed ? 'responsive' : 'non_responsive');
                $offer->model->forceFill(['status' => $status])->save();
            }
            Bidding::where('procurement_project_id', $project->id)->where('status', 'closed')->update(['status' => 'evaluated']);
            ($this->transition)($project, ProcurementProjectStatus::PostQualification, $actor);
            AuditLog::record('evaluation.completed', $project, null, ['ranking' => $ranked->map(fn (Offer $o) => [$o->supplier()->name, $o->amount(), $o->passed])->all()]);

            return $ranked;
        });
    }

    /** @return Collection<int, Bid|Quotation> */
    public static function offers(ProcurementProject $project): Collection
    {
        $bids = Bid::with('supplier')->whereIn('bidding_id', Bidding::where('procurement_project_id', $project->id)->whereIn('status', ['closed', 'evaluated'])->select('id'))
            ->whereNotNull('opened_at')->where('is_late', false)->whereNull('withdrawn_at')->get();
        $quotations = Quotation::with(['supplier', 'rfq'])->whereIn('rfq_id', Rfq::where('procurement_project_id', $project->id)->where('status', 'closed')->select('id'))
            ->where('is_late', false)->get();

        return $bids->concat($quotations)->values();
    }

    public static function projectOf(Bid|Quotation $offer): ProcurementProject
    {
        return $offer instanceof Bid ? $offer->bidding->procurementProject : $offer->rfq->procurementProject;
    }
}
