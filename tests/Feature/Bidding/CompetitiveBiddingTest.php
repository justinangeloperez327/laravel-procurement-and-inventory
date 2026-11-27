<?php

use App\Domain\Bac\BacMeetings;
use App\Domain\Bidding\CompetitiveBidding;
use App\Domain\Bidding\Evaluation;
use App\Domain\Bidding\EvaluationCriteria;
use App\Domain\Bidding\PostQualifications;
use App\Domain\Bidding\Recommendation;
use App\Domain\Compliance\ConflictDeclarations;
use App\Domain\Documents\DocumentStore;
use App\Domain\Procurement\ProjectSchedule;
use App\Domain\Shared\WorkflowViolation;
use App\Domain\Suppliers\SupplierSanctions;
use App\Enums\ProcurementMode;
use App\Enums\ProcurementProjectStatus;
use App\Enums\SanctionType;
use App\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->c = biddingContext();
    $this->project = projectInSolicitation($this->c, ProcurementMode::CompetitiveBidding);
    $this->bidding = app(CompetitiveBidding::class)->open($this->c->officer, $this->project,
        now()->addDays(7)->setTime(9, 0)->toDateTimeString(), now()->addDays(7)->setTime(10, 0)->toDateTimeString(), '500.00');
    $this->bids = app(CompetitiveBidding::class);
    $this->envelope = fn (string $name) => ['financial' => UploadedFile::fake()->createWithContent("{$name}.pdf", "%PDF-1.4 offer of {$name}")];
    $this->openingMeeting = function () {
        $meetings = app(BacMeetings::class);
        $meeting = $meetings->schedule($this->c->secretariat, $this->c->committee, 'bid_opening', now()->toDateTimeString(), $this->project);

        return $meetings->recordHeld($this->c->secretariat, $meeting, [$this->c->chair->id, $this->c->member1->id, $this->c->member2->id]);
    };
    [$this->alpha, $this->bravo, $this->charlie] = $this->c->suppliers;
});

it('locks criteria on publication and refuses changes or additions after solicitation (docs/16)', function () {
    $criterion = $this->project->criteria()->first();

    expect(fn () => $criterion->update(['criterion' => 'Relaxed requirement']))->toThrow(LogicException::class)
        ->and(fn () => $criterion->delete())->toThrow(LogicException::class)
        ->and(fn () => app(EvaluationCriteria::class)->add($this->c->officer, $this->project, 'New criterion', 'pass_fail'))
        ->toThrow(WorkflowViolation::class, 'no longer be added');
});

it('records an immutable server-time receipt and seals the envelopes', function () {
    $bid = $this->bids->receive($this->c->secretariat, $this->bidding, $this->alpha, ($this->envelope)('alpha'));

    expect($bid->receipt_reference)->toStartWith('RCPT-')
        ->and($bid->bid_amount)->toBeNull()
        ->and($bid->is_late)->toBeFalse()
        ->and(fn () => $bid->update(['submitted_at' => now()->subDay()]))->toThrow(LogicException::class)
        ->and(fn () => $bid->update(['bid_amount' => '1.00']))->toThrow(LogicException::class)
        ->and(fn () => $bid->delete())->toThrow(LogicException::class);
});

it('keeps bids sealed until opening and records attempts to read them early (docs/16)', function () {
    $bid = $this->bids->receive($this->c->secretariat, $this->bidding, $this->alpha, ($this->envelope)('alpha'));
    $version = $bid->documents()->sole()->document->versions()->sole();
    $url = app(DocumentStore::class)->temporaryUrl($version);

    $this->actingAs($this->c->chair)->get($url)->assertForbidden();
    expect(AuditLog::where('event', 'document.download_denied')->count())->toBe(1);

    $this->travelTo($this->bidding->bid_opening_at->copy()->subMinute());
    expect(fn () => $this->bids->openBids($this->c->chair, $this->bidding, ($this->openingMeeting)(), [$bid->id => '20000.00']))
        ->toThrow(WorkflowViolation::class, 'before the scheduled opening time');
    expect(AuditLog::where('event', 'bid.early_opening_denied')->exists())->toBeTrue();

    $this->travelTo($this->bidding->bid_opening_at->copy()->addMinute());
    $this->bids->openBids($this->c->chair, $this->bidding, ($this->openingMeeting)(), [$bid->id => '20000.00']);

    $this->actingAs($this->c->chair)->get(app(DocumentStore::class)->temporaryUrl($version))->assertOk();
    expect($bid->fresh()->bid_amount)->toBe('20000.00')->and($this->project->fresh()->status)->toBe(ProcurementProjectStatus::Evaluation);
});

it('returns late bids unopened, honouring deadline extensions (docs/16)', function () {
    $schedule = app(ProjectSchedule::class);
    $deadline = ProjectSchedule::currentDeadlines($this->project)->firstWhere('deadline_type', 'bid_submission');
    $schedule->extendDeadline($this->c->officer, $deadline, now()->addDays(8)->setTime(9, 0)->toDateTimeString(), 'Bid bulletin 1');

    $this->travelTo(now()->addDays(7)->setTime(12, 0)); // after the original deadline, before the extended one
    $onTime = $this->bids->receive($this->c->secretariat, $this->bidding, $this->alpha);
    $this->travelTo(now()->addDays(2));
    $late = $this->bids->receive($this->c->secretariat, $this->bidding, $this->bravo);

    expect($onTime->is_late)->toBeFalse()
        ->and($late->is_late)->toBeTrue()
        ->and($late->status)->toBe('returned_unopened');

    $this->bids->openBids($this->c->chair, $this->bidding, ($this->openingMeeting)(), [$onTime->id => '24000.00']);
    expect($late->fresh()->opened_at)->toBeNull()->and($late->fresh()->bid_amount)->toBeNull();
});

it('refuses bids from ineligible suppliers', function () {
    app(SupplierSanctions::class)->record($this->c->hope, $this->charlie, SanctionType::Blacklisting, 'GPPB', 'GPPB-2027-9', 'Fraud', now()->toDateString());

    $this->bids->receive($this->c->secretariat, $this->bidding, $this->charlie);
})->throws(WorkflowViolation::class, 'not eligible');

it('allows withdrawal only before the deadline', function () {
    $bid = $this->bids->receive($this->c->secretariat, $this->bidding, $this->alpha);
    $this->bids->withdraw($this->c->secretariat, $bid, 'Bidder request');
    $again = $this->bids->receive($this->c->secretariat, $this->bidding, $this->alpha);

    $this->travelTo($this->bidding->bid_submission_deadline->copy()->addMinute());
    expect(fn () => $this->bids->withdraw($this->c->secretariat, $again, 'Too late'))->toThrow(WorkflowViolation::class, 'after the submission deadline');
});

it('opens bids only at a quorate BAC meeting, by a member present', function () {
    $bid = $this->bids->receive($this->c->secretariat, $this->bidding, $this->alpha);
    $this->travelTo($this->bidding->bid_opening_at->copy()->addMinute());
    $meetings = app(BacMeetings::class);
    $noQuorum = $meetings->recordHeld($this->c->secretariat, $meetings->schedule($this->c->secretariat, $this->c->committee, 'bid_opening', now()->toDateTimeString(), $this->project), [$this->c->chair->id]);

    expect(fn () => $this->bids->openBids($this->c->chair, $this->bidding, $noQuorum, [$bid->id => '1.00']))->toThrow(WorkflowViolation::class, 'quorum');

    $meeting = $meetings->recordHeld($this->c->secretariat, $meetings->schedule($this->c->secretariat, $this->c->committee, 'bid_opening', now()->toDateTimeString(), $this->project), [$this->c->chair->id, $this->c->member1->id]);
    expect(fn () => $this->bids->openBids($this->c->member2, $this->bidding, $meeting, [$bid->id => '1.00']))->toThrow(WorkflowViolation::class, 'present');
});

it('runs evaluation, ranking, post-qualification and recommendation', function () {
    $a = $this->bids->receive($this->c->secretariat, $this->bidding, $this->alpha);
    $b = $this->bids->receive($this->c->secretariat, $this->bidding, $this->bravo);
    $ch = $this->bids->receive($this->c->secretariat, $this->bidding, $this->charlie);
    $this->travelTo($this->bidding->bid_opening_at->copy()->addMinute());
    $this->bids->openBids($this->c->chair, $this->bidding, ($this->openingMeeting)(), [$a->id => '21000.00', $b->id => '19500.00', $ch->id => '23000.00']);

    evaluateOffer($this->c, $a->fresh());
    evaluateOffer($this->c, $b->fresh(), 'fail');   // lowest, but fails technical
    expect(fn () => app(Evaluation::class)->finish($this->c->chair, $this->project->fresh()))->toThrow(WorkflowViolation::class, 'Every offer');
    evaluateOffer($this->c, $ch->fresh());

    $ranked = app(Evaluation::class)->finish($this->c->chair, $this->project->fresh());
    expect($ranked->map(fn ($o) => [$o->supplier()->name, $o->passed])->all())
        ->toBe([['Alpha Trading', true], ['Charlie Corp', true], ['Bravo Supply', false]])
        ->and($b->fresh()->status)->toBe('failed')
        ->and($this->project->fresh()->status)->toBe(ProcurementProjectStatus::PostQualification);

    $pqs = app(PostQualifications::class);
    $first = $pqs->startNext($this->c->member1, $this->project->fresh());
    expect($first->supplier_id)->toBe($this->alpha->id);
    $pqs->record($this->c->member1, $first, false, 'Could not show the required stock on site visit');

    $second = $pqs->startNext($this->c->member1, $this->project->fresh());
    expect($second->supplier_id)->toBe($this->charlie->id);
    $pqs->record($this->c->member2, $second, true, 'All documents verified; site visit satisfactory');

    $meeting = ($this->openingMeeting)();
    $resolution = app(BacMeetings::class)->adoptResolution($this->c->chair, $meeting, 'recommend_award', 'Recommending award to Charlie Corp');
    app(Recommendation::class)->recommendAward($this->c->chair, $this->project->fresh(), $resolution);

    expect($this->project->fresh()->status)->toBe(ProcurementProjectStatus::Recommended);
});

it('keeps conflicted evaluators out (docs/16)', function () {
    $bid = $this->bids->receive($this->c->secretariat, $this->bidding, $this->alpha);
    $this->travelTo($this->bidding->bid_opening_at->copy()->addMinute());
    $this->bids->openBids($this->c->chair, $this->bidding, ($this->openingMeeting)(), [$bid->id => '21000.00']);
    $criterion = $this->project->criteria()->first();

    $this->travel(1)->minutes();
    app(ConflictDeclarations::class)->declare($this->c->member1, null, $this->alpha, true, 'Brother-in-law is the owner', 'recused');

    expect(fn () => app(Evaluation::class)->evaluate($this->c->member1, $bid->fresh(), $criterion, 'pass'))->toThrow(WorkflowViolation::class, 'recuse');
    expect(app(Evaluation::class)->evaluate($this->c->member2, $bid->fresh(), $criterion, 'pass')->exists)->toBeTrue()
        ->and(fn () => app(Evaluation::class)->evaluate($this->c->member2, $bid->fresh(), $criterion, 'fail'))->toThrow(QueryException::class);
});

it('fails post-qualification automatically when the supplier became ineligible', function () {
    $bid = $this->bids->receive($this->c->secretariat, $this->bidding, $this->alpha);
    $this->travelTo($this->bidding->bid_opening_at->copy()->addMinute());
    $this->bids->openBids($this->c->chair, $this->bidding, ($this->openingMeeting)(), [$bid->id => '21000.00']);
    evaluateOffer($this->c, $bid->fresh());
    app(Evaluation::class)->finish($this->c->chair, $this->project->fresh());

    app(SupplierSanctions::class)->record($this->c->hope, $this->alpha, SanctionType::Suspension, 'HoPE', 'HoPE-7', 'Delay in other contract', now()->toDateString());
    $pq = app(PostQualifications::class)->startNext($this->c->member1, $this->project->fresh());

    expect($pq->result)->toBe('failed')->and($pq->findings)->toContain('Suspension');
});
