<?php

use App\Domain\Procurement\CreateProcurementProject;
use App\Domain\Procurement\ProjectLots;
use App\Domain\Procurement\ProjectSchedule;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\ProcurementCategory;
use App\Enums\ProcurementProjectStatus;
use App\Enums\PurchaseRequestStatus;

beforeEach(function () {
    $this->c = procurementContext();
    $this->create = fn (array $lineIds, string $title = 'Office supplies Q1') => app(CreateProcurementProject::class)(
        $this->c->officer, $this->c->fy, $title, ProcurementCategory::Goods, $lineIds,
    );
});

it('builds a project from approved request lines with an exact ABC', function () {
    $pr1 = approvedRequest($this->c, '3', '33.3333');
    $pr2 = approvedRequest($this->c, '10', '250.00');
    $project = ($this->create)([$pr1->items()->value('id'), $pr2->items()->value('id')]);

    expect($project->reference_no)->toBe('PROJ-2027-0001')
        ->and($project->status)->toBe(ProcurementProjectStatus::Proposed)
        ->and($project->approved_budget)->toBe('2600.00')
        ->and($project->app_item_id)->toBe($this->c->appItem->id)
        ->and($project->end_user_department_id)->toBe($this->c->dept->id)
        ->and($pr1->fresh()->status)->toBe(PurchaseRequestStatus::Procurement);
});

it('refuses lines of requests that are not approved', function () {
    $pr = draftRequest($this->c);

    ($this->create)([$pr->items()->value('id')]);
})->throws(WorkflowViolation::class, 'not approved');

it('keeps each line in one active project, and frees it when that project is cancelled', function () {
    $line = approvedRequest($this->c)->items()->value('id');
    $project = ($this->create)([$line]);

    expect(fn () => ($this->create)([$line]))->toThrow(WorkflowViolation::class, 'already in an active procurement project');

    app(ProjectSchedule::class)->cancel($this->c->officer, $project, 'Requirement merged into another project');
    expect(($this->create)([$line], 'Re-procurement')->status)->toBe(ProcurementProjectStatus::Proposed);
});

it('requires procurement.manage', function () {
    app(CreateProcurementProject::class)($this->c->requester, $this->c->fy, 'X', ProcurementCategory::Goods, [approvedRequest($this->c)->items()->value('id')]);
})->throws(WorkflowViolation::class, 'procurement.manage');

it('groups lines into lots whose ABC is the sum of their lines', function () {
    $a = approvedRequest($this->c, '10', '250.00')->items()->value('id');
    $b = approvedRequest($this->c, '4', '100.00')->items()->value('id');
    $project = ($this->create)([$a, $b]);
    $items = $project->items()->orderBy('id')->pluck('id')->all();

    $lot1 = app(ProjectLots::class)->create($this->c->officer, $project, 'Paper', [$items[0]]);
    $lot2 = app(ProjectLots::class)->create($this->c->officer, $project, 'Other', [$items[1]]);

    expect([$lot1->lot_no, $lot2->lot_no])->toBe([1, 2])
        ->and($lot1->approved_budget)->toBe('2500.00')
        ->and($lot2->approved_budget)->toBe('400.00')
        ->and(fn () => app(ProjectLots::class)->create($this->c->officer, $project, 'Again', [$items[0]]))->toThrow(WorkflowViolation::class, 'already in a lot');
});

it('extends deadlines by adding linked rows, never overwriting', function () {
    $project = ($this->create)([approvedRequest($this->c)->items()->value('id')]);
    $schedule = app(ProjectSchedule::class);
    $first = $schedule->setDeadline($this->c->officer, $project, 'bid_submission', now()->addDays(7)->toDateTimeString(), 'RFQ posting period');

    expect(fn () => $schedule->setDeadline($this->c->officer, $project, 'bid_submission', now()->addDays(9)->toDateTimeString()))->toThrow(WorkflowViolation::class, 'Extend it')
        ->and(fn () => $schedule->extendDeadline($this->c->officer, $first, now()->addDays(6)->toDateTimeString(), 'x'))->toThrow(WorkflowViolation::class, 'later')
        ->and(fn () => $first->update(['due_at' => now()->addYear()]))->toThrow(LogicException::class);

    $second = $schedule->extendDeadline($this->c->officer, $first, now()->addDays(10)->toDateTimeString(), 'Bid bulletin no. 1');
    expect($second->extended_from_id)->toBe($first->id)
        ->and(fn () => $schedule->extendDeadline($this->c->officer, $first, now()->addDays(12)->toDateTimeString(), 'x'))->toThrow(WorkflowViolation::class, 'latest')
        ->and(ProjectSchedule::currentDeadlines($project)->pluck('id')->all())->toBe([$second->id]);
});

it('records publications and events', function () {
    $project = ($this->create)([approvedRequest($this->c)->items()->value('id')]);
    $schedule = app(ProjectSchedule::class);
    $schedule->recordPublication($this->c->officer, $project, 'philgeps', now()->toDateTimeString(), 'PG-REF-123');
    $event = $schedule->scheduleEvent($this->c->officer, $project, 'pre_bid_conference', now()->addDays(3)->toDateTimeString(), 'Conference room');
    $schedule->recordEventHeld($this->c->officer, $event, now()->addDays(3)->toDateTimeString(), 'Two suppliers attended');

    expect($project->publications()->sole()->reference_no)->toBe('PG-REF-123')
        ->and($event->fresh()->held_at)->not->toBeNull();
});
