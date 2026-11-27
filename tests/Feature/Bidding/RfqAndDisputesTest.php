<?php

use App\Domain\Bac\BacMeetings;
use App\Domain\Bidding\CompetitiveBidding;
use App\Domain\Bidding\Disputes;
use App\Domain\Bidding\Evaluation;
use App\Domain\Bidding\Recommendation;
use App\Domain\Bidding\Solicitations;
use App\Domain\Procurement\CreateProcurementProject;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\ProcurementMode;
use App\Enums\ProcurementProjectStatus;
use App\Models\ComplianceFlag;

beforeEach(function () {
    $this->c = biddingContext();
    $this->project = projectInSolicitation($this->c, ProcurementMode::SmallValueProcurement);
    $this->rfqs = app(Solicitations::class);
    $this->rfq = $this->rfqs->issueRfq($this->c->officer, $this->project, now()->addDays(3)->toDateTimeString(), 3);
    $this->line = $this->project->items()->sole();
    $this->quote = fn ($supplier, string $price) => $this->rfqs->recordQuotation($this->c->secretariat, $this->rfq, $supplier, [$this->line->id => $price]);
    [$this->alpha, $this->bravo, $this->charlie] = $this->c->suppliers;
});

it('records quotations with exact totals and marks late ones', function () {
    $q = ($this->quote)($this->alpha, '240.5000');
    expect($q->total_amount)->toBe('24050.00')->and($q->is_late)->toBeFalse();

    $this->travelTo($this->rfq->submission_deadline->copy()->addMinute());
    $late = ($this->quote)($this->bravo, '200.00');
    expect($late->is_late)->toBeTrue()->and($late->status)->toBe('late');
});

it('refuses competitive bidding actions for RFQ projects and a second active solicitation', function () {
    expect(fn () => $this->rfqs->issueRfq($this->c->officer, $this->project, now()->addDays(3)->toDateTimeString()))->toThrow(WorkflowViolation::class, 'active solicitation')
        ->and(fn () => app(CompetitiveBidding::class)->open($this->c->officer, $this->project, now()->addDay()->toDateTimeString(), now()->addDay()->toDateTimeString()))
        ->toThrow(WorkflowViolation::class, 'competitive bidding');
});

it('closes the RFQ only after its deadline and flags too few quotations', function () {
    ($this->quote)($this->alpha, '240.00');
    ($this->quote)($this->bravo, '235.00');

    expect(fn () => $this->rfqs->closeRfq($this->c->officer, $this->rfq))->toThrow(WorkflowViolation::class, 'before its deadline');

    $this->travelTo($this->rfq->submission_deadline->copy()->addMinute());
    $this->rfqs->closeRfq($this->c->officer, $this->rfq);
    expect($this->project->fresh()->status)->toBe(ProcurementProjectStatus::Evaluation)
        ->and(ComplianceFlag::where('flag_type', 'insufficient_quotations')->sole()->description)->toContain('2 on-time quotations');
});

it('ranks quotations by lowest responsive price', function () {
    $a = ($this->quote)($this->alpha, '240.00');
    $b = ($this->quote)($this->bravo, '235.00');
    $ch = ($this->quote)($this->charlie, '250.00');
    $this->travelTo($this->rfq->submission_deadline->copy()->addMinute());
    $this->rfqs->closeRfq($this->c->officer, $this->rfq);

    evaluateOffer($this->c, $a);
    evaluateOffer($this->c, $b);
    evaluateOffer($this->c, $ch);
    $ranked = app(Evaluation::class)->finish($this->c->chair, $this->project->fresh());

    expect($ranked->map(fn ($o) => $o->supplier()->name)->all())->toBe(['Bravo Supply', 'Alpha Trading', 'Charlie Corp'])
        ->and($b->fresh()->status)->toBe('responsive');
});

it('requires a denied reconsideration before a protest, and the protest blocks award until decided', function () {
    $q = ($this->quote)($this->alpha, '240.00');
    $disputes = app(Disputes::class);

    expect(fn () => $disputes->fileProtest($this->c->officer, $this->project, $this->alpha, 'Unfair evaluation'))->toThrow(WorkflowViolation::class, 'reconsideration');

    $mr = $disputes->fileReconsideration($this->c->officer, $this->project, $this->alpha, 'Our quotation was compliant');
    $disputes->decideReconsideration($this->c->chair, $mr, false, 'Specifications not met');
    $protest = $disputes->fileProtest($this->c->officer, $this->project, $this->alpha, 'Unfair evaluation', '5000.00');

    expect(Disputes::blocksAward($this->project))->toBeTrue()
        ->and(fn () => $disputes->decideProtest($this->c->chair, $protest, false, 'x'))->toThrow(WorkflowViolation::class, 'award.approve');

    $disputes->decideProtest($this->c->hope, $protest, false, 'Protest denied; evaluation upheld');
    expect(Disputes::blocksAward($this->project))->toBeFalse();
});

it('rejects disputes from suppliers who did not take part', function () {
    app(Disputes::class)->fileReconsideration($this->c->officer, $this->project, $this->charlie, 'x');
})->throws(WorkflowViolation::class, 'did not take part');

it('declares failure by resolution and frees the request lines', function () {
    $meeting = quorateMeeting($this->c, $this->project);
    $resolution = app(BacMeetings::class)->adoptResolution($this->c->chair, $meeting, 'declare_failure', 'No quotations received');

    app(Recommendation::class)->declareFailure($this->c->chair, $this->project, $resolution);

    expect($this->project->fresh()->status)->toBe(ProcurementProjectStatus::Failed)
        ->and($this->rfq->fresh()->status)->toBe('failed')
        ->and(CreateProcurementProject::lineInActiveProject($this->line->purchase_request_item_id))->toBeFalse();
});
