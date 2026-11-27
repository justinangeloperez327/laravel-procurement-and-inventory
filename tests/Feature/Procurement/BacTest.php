<?php

use App\Domain\Bac\BacComposition;
use App\Domain\Bac\BacMeetings;
use App\Domain\Shared\WorkflowViolation;
use App\Models\User;

beforeEach(function () {
    $this->c = procurementContext();
    $this->bac = app(BacComposition::class);
    $this->meetings = app(BacMeetings::class);
    $this->meeting = fn () => $this->meetings->schedule($this->c->secretariat, $this->c->committee, 'regular', now()->toDateTimeString());
});

it('lets only the HoPE constitute the BAC and requires matching roles', function () {
    $plain = User::factory()->forAgency($this->c->agency)->create();
    grant($plain, 'end_user', $this->c->dept);

    expect(fn () => $this->bac->constitute($this->c->chair, 'X', 'goods', 'OO'))->toThrow(WorkflowViolation::class, 'bac.constitute')
        ->and(fn () => $this->bac->addMember($this->c->hope, $this->c->committee, $plain, 'member'))->toThrow(WorkflowViolation::class, 'BAC Member')
        ->and(fn () => $this->bac->addMember($this->c->hope, $this->c->committee, $this->c->member1, 'member'))->toThrow(WorkflowViolation::class, 'already a member');

    $another = User::factory()->forAgency($this->c->agency)->create();
    grant($another, 'bac_chair');
    expect(fn () => $this->bac->addMember($this->c->hope, $this->c->committee, $another, 'chair'))->toThrow(WorkflowViolation::class, 'already has a chairperson');
});

it('computes quorum from attendance', function (array $present, bool $quorum) {
    $ids = array_map(fn (string $k) => $this->c->{$k}->id, $present);
    $held = $this->meetings->recordHeld($this->c->secretariat, ($this->meeting)(), $ids, ['COA observer']);

    expect($held->quorum_met)->toBe($quorum)
        ->and($held->attendees()->where('attendee_role', 'observer')->count())->toBe(1);
})->with([
    'chair and one member (2 of 3)' => [['chair', 'member1'], true],
    'all members' => [['chair', 'member1', 'member2'], true],
    'chair alone (1 of 3)' => [['chair'], false],
    'two members without a presiding officer' => [['member1', 'member2'], false],
]);

it('adopts resolutions only at a quorate meeting, by a voting member present', function () {
    $noQuorum = $this->meetings->recordHeld($this->c->secretariat, ($this->meeting)(), [$this->c->chair->id]);
    expect(fn () => $this->meetings->adoptResolution($this->c->chair, $noQuorum, 'recommend_mode', 'X'))->toThrow(WorkflowViolation::class, 'no quorum');

    $meeting = $this->meetings->recordHeld($this->c->secretariat, ($this->meeting)(), [$this->c->chair->id, $this->c->member1->id]);
    expect(fn () => $this->meetings->adoptResolution($this->c->member2, $meeting, 'recommend_mode', 'X'))->toThrow(WorkflowViolation::class, 'present at the meeting');

    $resolution = $this->meetings->adoptResolution($this->c->member1, $meeting, 'recommend_mode', 'Recommending SVP');
    expect($resolution->resolution_no)->toBe('BACRES-'.now()->year.'-0001')
        ->and(fn () => $resolution->update(['title' => 'Edited']))->toThrow(LogicException::class);
});

it('freezes a meeting once attendance is recorded', function () {
    $meeting = $this->meetings->recordHeld($this->c->secretariat, ($this->meeting)(), [$this->c->chair->id, $this->c->member1->id]);

    expect(fn () => $meeting->update(['quorum_met' => false]))->toThrow(LogicException::class)
        ->and(fn () => $this->meetings->recordHeld($this->c->secretariat, $meeting, []))->toThrow(WorkflowViolation::class, 'already recorded');
});

it('refuses attendees who are not on the committee', function () {
    $this->meetings->recordHeld($this->c->secretariat, ($this->meeting)(), [$this->c->requester->id]);
})->throws(WorkflowViolation::class, 'not on this committee');

it('counts only members serving on the meeting date', function () {
    $this->bac->endMembership($this->c->hope, $this->c->committee->members()->where('user_id', $this->c->member2->id)->sole(), now()->subDay()->toDateString(), 'Reassigned');

    // 2 voting members remain; the chair alone is not a majority.
    expect($this->meetings->recordHeld($this->c->secretariat, ($this->meeting)(), [$this->c->chair->id])->quorum_met)->toBeFalse()
        ->and($this->meetings->recordHeld($this->c->secretariat, ($this->meeting)(), [$this->c->chair->id, $this->c->member1->id])->quorum_met)->toBeTrue();
});
