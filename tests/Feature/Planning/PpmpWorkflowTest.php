<?php

use App\Domain\Planning\CreateMarketScoping;
use App\Domain\Planning\CreatePpmp;
use App\Domain\Planning\PlanApproval;
use App\Domain\Planning\RevisePpmp;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\ApprovalDecision;
use App\Enums\PlanStatus;
use App\Enums\ProcurementMode;
use App\Models\Department;
use App\Models\Ppmp;
use App\Models\StatusHistory;
use App\Models\UnitOfMeasure;
use App\Models\User;

beforeEach(function () {
    $this->c = planningContext();
    $this->plans = app(PlanApproval::class);
    $this->uom = UnitOfMeasure::factory()->create();
    $this->newPpmp = fn () => app(CreatePpmp::class)($this->c->planner, $this->c->dept, $this->c->fy);
    $this->addItem = fn (Ppmp $ppmp, string $qty = '10', string $cost = '250.00', ?ProcurementMode $mode = ProcurementMode::SmallValueProcurement) => $ppmp->items()->create([
        'description' => 'Bond paper, A4', 'unit_of_measure_id' => $this->uom->id, 'quantity' => $qty,
        'estimated_unit_cost' => $cost, 'proposed_mode' => $mode,
    ]);
    $this->approved = function () {
        $ppmp = ($this->newPpmp)();
        ($this->addItem)($ppmp);
        $this->plans->submit($this->c->planner, $ppmp);
        $this->plans->decide($this->c->head, $ppmp, ApprovalDecision::Approved);

        return $ppmp->fresh();
    };
});

it('numbers PPMPs per agency and fiscal year', function () {
    expect(($this->newPpmp)()->reference_no)->toBe('PPMP-2027-0001')
        ->and(($this->newPpmp)()->reference_no)->toBe('PPMP-2027-0002');
});

it('requires ppmp.create for the department', function () {
    app(CreatePpmp::class)($this->c->head, $this->c->dept, $this->c->fy);
})->throws(WorkflowViolation::class, 'ppmp.create');

it('computes line budgets and totals exactly in the database', function () {
    $ppmp = ($this->newPpmp)();
    $line = ($this->addItem)($ppmp, '3', '33.3333');
    ($this->addItem)($ppmp, '0.5', '0.01');
    $this->plans->submit($this->c->planner, $ppmp);

    expect($line->fresh()->estimated_budget)->toBe('100.00')
        ->and($ppmp->fresh()->total_amount)->toBe('100.01');
});

it('runs submit, department approval and records history', function () {
    $ppmp = ($this->approved)();

    expect($ppmp->status)->toBe(PlanStatus::Approved)
        ->and($ppmp->approved_by)->toBe($this->c->head->id)
        ->and(StatusHistory::where('subject_type', $ppmp->getMorphClass())->where('subject_id', $ppmp->id)->pluck('to_status')->all())
        ->toBe(['submitted', 'approved']);
});

it('blocks submission of empty PPMPs or items without a proposed mode', function () {
    $ppmp = ($this->newPpmp)();
    expect(fn () => $this->plans->submit($this->c->planner, $ppmp))->toThrow(WorkflowViolation::class, 'at least one item');

    ($this->addItem)($ppmp, '1', '1', null);
    expect(fn () => $this->plans->submit($this->c->planner, $ppmp))->toThrow(WorkflowViolation::class, 'proposed procurement mode');
});

it('freezes a PPMP and its lines once submitted', function () {
    $ppmp = ($this->newPpmp)();
    $line = ($this->addItem)($ppmp);
    $this->plans->submit($this->c->planner, $ppmp);

    expect(fn () => $ppmp->fresh()->update(['remarks' => 'edited']))->toThrow(LogicException::class)
        ->and(fn () => $line->fresh()->update(['quantity' => 99]))->toThrow(LogicException::class)
        ->and(fn () => ($this->addItem)($ppmp->fresh()))->toThrow(LogicException::class);
});

it('returns with remarks and accepts resubmission', function () {
    $ppmp = ($this->newPpmp)();
    $line = ($this->addItem)($ppmp);
    $this->plans->submit($this->c->planner, $ppmp);

    expect(fn () => $this->plans->decide($this->c->head, $ppmp, ApprovalDecision::Returned))->toThrow(WorkflowViolation::class, 'Remarks');
    $this->plans->decide($this->c->head, $ppmp, ApprovalDecision::Returned, 'Split the paper by size');

    $line->fresh()->update(['quantity' => 12]);
    $this->plans->submit($this->c->planner, $ppmp->fresh());
    $this->plans->decide($this->c->head, $ppmp->fresh(), ApprovalDecision::Approved);

    expect($ppmp->fresh()->status)->toBe(PlanStatus::Approved)->and($ppmp->fresh()->total_amount)->toBe('3000.00');
});

it('does not let the preparer or another department approve', function () {
    $ppmp = ($this->newPpmp)();
    ($this->addItem)($ppmp);
    $this->plans->submit($this->c->planner, $ppmp);
    grant($this->c->planner, 'department_head', $this->c->dept);

    expect(fn () => $this->plans->decide($this->c->planner, $ppmp, ApprovalDecision::Approved))->toThrow(WorkflowViolation::class, 'initiator');

    $otherHead = User::factory()->forAgency($this->c->agency)->create();
    grant($otherHead, 'department_head', Department::factory()->for($this->c->agency)->create());
    expect(fn () => $this->plans->decide($otherHead, $ppmp, ApprovalDecision::Approved))->toThrow(WorkflowViolation::class);
});

it('does not reject plans outright', function () {
    $ppmp = ($this->newPpmp)();
    ($this->addItem)($ppmp);
    $this->plans->submit($this->c->planner, $ppmp);

    $this->plans->decide($this->c->head, $ppmp, ApprovalDecision::Rejected, 'No');
})->throws(WorkflowViolation::class, 'returned with remarks');

it('revises an approved PPMP as a new version that supersedes it on approval', function () {
    $v1 = ($this->approved)();
    $v2 = app(RevisePpmp::class)($this->c->planner, $v1, 'Mid-year adjustment');

    expect($v2->version)->toBe(2)
        ->and($v2->reference_no)->toBe($v1->reference_no)
        ->and($v2->parent_ppmp_id)->toBe($v1->id)
        ->and($v2->items)->toHaveCount(1)
        ->and($v1->fresh()->status)->toBe(PlanStatus::Approved);

    expect(fn () => app(RevisePpmp::class)($this->c->planner, $v1, 'Again'))->toThrow(WorkflowViolation::class, 'already in progress');

    $v2->items()->first()->update(['quantity' => 20]);
    $this->plans->submit($this->c->planner, $v2);
    $this->plans->decide($this->c->head, $v2, ApprovalDecision::Approved);

    expect($v1->fresh()->status)->toBe(PlanStatus::Superseded)
        ->and($v2->fresh()->status)->toBe(PlanStatus::Approved)
        ->and($v1->fresh()->items()->first()->quantity)->toBe('10.0000')
        ->and($v1->versions()->pluck('version')->all())->toBe([2, 1]);
});

it('blocks planning in a closed fiscal year', function () {
    $ppmp = ($this->newPpmp)();
    ($this->addItem)($ppmp);
    $this->c->fy->update(['status' => 'closed']);

    $this->plans->submit($this->c->planner, $ppmp);
})->throws(WorkflowViolation::class, 'closed');

it('runs market scoping through the same review', function () {
    $ms = app(CreateMarketScoping::class)($this->c->planner, $this->c->dept, $this->c->fy, 'Office supplies canvass');
    $ms->items()->create(['description' => 'Bond paper', 'estimated_unit_cost' => '245.50', 'price_source' => 'canvass of 3 suppliers']);
    $this->plans->submit($this->c->planner, $ms);
    $this->plans->decide($this->c->head, $ms, ApprovalDecision::Approved);

    expect($ms->fresh()->status)->toBe(PlanStatus::Approved)->and($ms->reference_no)->toBe('MS-2027-0001');
});
