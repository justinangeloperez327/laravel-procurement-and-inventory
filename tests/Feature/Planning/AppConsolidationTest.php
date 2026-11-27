<?php

use App\Domain\Planning\AppCoverage;
use App\Domain\Planning\ConsolidatePpmps;
use App\Domain\Planning\CreateApp;
use App\Domain\Planning\CreatePpmp;
use App\Domain\Planning\PlanApproval;
use App\Domain\Planning\ReviseApp;
use App\Domain\Planning\RevisePpmp;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\ApprovalDecision;
use App\Enums\PlanStatus;
use App\Enums\ProcurementMode;
use App\Models\AppVersion;
use App\Models\Department;
use App\Models\FundSource;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Ppmp;
use App\Models\UnitOfMeasure;
use App\Models\User;

beforeEach(function () {
    $this->c = planningContext();
    $this->plans = app(PlanApproval::class);
    $this->consolidate = app(ConsolidatePpmps::class);
    $this->uom = UnitOfMeasure::factory()->create();
    $this->paper = Item::factory()->create(['agency_id' => $this->c->agency->id, 'item_category_id' => ItemCategory::factory()->create(['name' => 'Office supplies'])->id]);
    $this->laptop = Item::factory()->create(['agency_id' => $this->c->agency->id, 'item_category_id' => ItemCategory::factory()->create(['name' => 'ICT equipment'])->id]);

    $this->approvedPpmp = function (Department $dept, User $planner, User $head, array $lines): Ppmp {
        $ppmp = app(CreatePpmp::class)($planner, $dept, $this->c->fy);
        foreach ($lines as [$item, $qty, $cost, $mode]) {
            $ppmp->items()->create(['item_id' => $item->id, 'description' => $item->name, 'unit_of_measure_id' => $this->uom->id,
                'quantity' => $qty, 'estimated_unit_cost' => $cost, 'proposed_mode' => $mode]);
        }
        $this->plans->submit($planner, $ppmp);
        $this->plans->decide($head, $ppmp, ApprovalDecision::Approved);

        return $ppmp->fresh();
    };

    $svp = ProcurementMode::SmallValueProcurement;
    $this->ppmpA = ($this->approvedPpmp)($this->c->dept, $this->c->planner, $this->c->head, [
        [$this->paper, '100', '250.00', $svp], [$this->laptop, '2', '60000.00', ProcurementMode::CompetitiveBidding],
    ]);

    $this->dept2 = Department::factory()->for($this->c->agency)->create();
    $planner2 = User::factory()->forAgency($this->c->agency)->create();
    grant($planner2, 'planning_officer', $this->dept2);
    $head2 = User::factory()->forAgency($this->c->agency)->create();
    grant($head2, 'department_head', $this->dept2);
    $this->ppmpB = ($this->approvedPpmp)($this->dept2, $planner2, $head2, [[$this->paper, '40', '250.00', $svp]]);
    $this->planner2 = $planner2;
    $this->head2 = $head2;
});

function approveApp(object $test, AppVersion $version): AppVersion
{
    $test->plans->submit($test->c->appPlanner, $version);
    $test->plans->decide($test->c->hope, $version, ApprovalDecision::Approved);

    return $version->fresh();
}

it('opens one APP per fiscal year', function () {
    $v1 = app(CreateApp::class)($this->c->appPlanner, $this->c->fy);

    expect($v1->version)->toBe(1)->and($v1->app->reference_no)->toBe('APP-2027-0001')
        ->and(fn () => app(CreateApp::class)($this->c->appPlanner, $this->c->fy))->toThrow(WorkflowViolation::class, 'already exists');
});

it('needs agency-wide app.consolidate', function () {
    app(CreateApp::class)($this->c->planner, $this->c->fy);
})->throws(WorkflowViolation::class, 'app.consolidate');

it('consolidates approved PPMP items with lineage and exact sums', function () {
    $v1 = app(CreateApp::class)($this->c->appPlanner, $this->c->fy);
    $result = ($this->consolidate)($this->c->appPlanner, $v1);

    $items = $v1->items()->with('sources')->orderBy('code')->get();
    $paper = $items->firstWhere('procurement_mode', ProcurementMode::SmallValueProcurement);

    expect($result)->toBe(['added' => 3, 'removed' => 0, 'items' => 2])
        ->and($paper->estimated_budget)->toBe('35000.00')
        ->and($paper->sources)->toHaveCount(2)
        ->and($items->firstWhere('procurement_mode', ProcurementMode::CompetitiveBidding)->estimated_budget)->toBe('120000.00')
        ->and($v1->fresh()->total_amount)->toBe('155000.00');

    expect(($this->consolidate)($this->c->appPlanner, $v1))->toBe(['added' => 0, 'removed' => 0, 'items' => 2]);
});

it('ignores draft and other-year PPMPs', function () {
    $draft = app(CreatePpmp::class)($this->c->planner, $this->c->dept, $this->c->fy);
    $draft->items()->create(['description' => 'x', 'unit_of_measure_id' => $this->uom->id, 'quantity' => 1, 'estimated_unit_cost' => '1', 'proposed_mode' => ProcurementMode::SmallValueProcurement]);
    $v1 = app(CreateApp::class)($this->c->appPlanner, $this->c->fy);

    expect(($this->consolidate)($this->c->appPlanner, $v1)['added'])->toBe(3);
});

it('lets only the approved APP support spending', function () {
    $v1 = app(CreateApp::class)($this->c->appPlanner, $this->c->fy);
    ($this->consolidate)($this->c->appPlanner, $v1);
    $item = $v1->items()->first();

    expect(AppCoverage::isUsable($item))->toBeFalse()
        ->and(AppCoverage::usableItems($this->c->agency->id)->count())->toBe(0);

    $v1 = approveApp($this, $v1);
    expect($v1->status)->toBe(PlanStatus::Approved)
        ->and($v1->app->status)->toBe(PlanStatus::Approved)
        ->and(AppCoverage::isUsable($item))->toBeTrue()
        ->and(AppCoverage::usableItems($this->c->agency->id, $this->c->fy->id)->count())->toBe(2);
});

it('requires HoPE authority to approve the APP', function () {
    $v1 = app(CreateApp::class)($this->c->appPlanner, $this->c->fy);
    ($this->consolidate)($this->c->appPlanner, $v1);
    $this->plans->submit($this->c->appPlanner, $v1);

    $this->plans->decide($this->c->head, $v1, ApprovalDecision::Approved);
})->throws(WorkflowViolation::class, 'app.approve');

it('revises the APP, keeping the approved version in force until the revision is approved', function () {
    $v1 = app(CreateApp::class)($this->c->appPlanner, $this->c->fy);
    ($this->consolidate)($this->c->appPlanner, $v1);
    $v1 = approveApp($this, $v1);

    // Department 2 revises its PPMP: 40 -> 60 reams.
    $ppmpB2 = app(RevisePpmp::class)($this->planner2, $this->ppmpB, 'Additional staff');
    $ppmpB2->items()->first()->update(['quantity' => 60]);
    $this->plans->submit($this->planner2, $ppmpB2);
    $this->plans->decide($this->head2, $ppmpB2, ApprovalDecision::Approved);

    $v2 = app(ReviseApp::class)($this->c->appPlanner, $v1->app, 'Updated PPMP of department 2');
    expect($v2->supersedes_id)->toBe($v1->id)
        ->and($v2->items)->toHaveCount(2)
        ->and(fn () => $this->plans->submit($this->c->appPlanner, $v2))->toThrow(WorkflowViolation::class, 'Consolidate again');

    expect(($this->consolidate)($this->c->appPlanner, $v2))->toMatchArray(['added' => 1, 'removed' => 1]);
    expect($v2->fresh()->total_amount)->toBe('160000.00')
        ->and(AppCoverage::isUsable($v1->items()->first()))->toBeTrue();

    $v2 = approveApp($this, $v2);
    expect($v1->fresh()->status)->toBe(PlanStatus::Superseded)
        ->and(AppCoverage::isUsable($v1->items()->first()))->toBeFalse()
        ->and(AppCoverage::usableItems($this->c->agency->id)->pluck('app_version_id')->unique()->all())->toBe([$v2->id])
        ->and($v1->fresh()->total_amount)->toBe('155000.00');
});

it('freezes approved APP versions and their lineage', function () {
    $v1 = app(CreateApp::class)($this->c->appPlanner, $this->c->fy);
    ($this->consolidate)($this->c->appPlanner, $v1);
    $v1 = approveApp($this, $v1);

    expect(fn () => $v1->items()->first()->update(['description' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $v1->items()->first()->sources()->first()->delete())->toThrow(LogicException::class)
        ->and(fn () => ($this->consolidate)($this->c->appPlanner, $v1))->toThrow(WorkflowViolation::class, 'draft or returned');
});

it('allows only one APP revision at a time', function () {
    $v1 = app(CreateApp::class)($this->c->appPlanner, $this->c->fy);
    expect(fn () => app(ReviseApp::class)($this->c->appPlanner, $v1->app, 'x'))->toThrow(WorkflowViolation::class, 'already being prepared');

    ($this->consolidate)($this->c->appPlanner, $v1);
    $v1 = approveApp($this, $v1);
    app(ReviseApp::class)($this->c->appPlanner, $v1->app, 'x');

    app(ReviseApp::class)($this->c->appPlanner, $v1->app, 'y');
})->throws(WorkflowViolation::class, 'already being prepared');

it('uses fund sources to group APP items', function () {
    $fund = FundSource::factory()->create(['agency_id' => $this->c->agency->id]);
    ($this->approvedPpmp)($this->c->dept, $this->c->planner, $this->c->head, [[$this->paper, '1', '250.00', ProcurementMode::SmallValueProcurement]]);
    Ppmp::latest('id')->first()->items()->first(); // approved, cannot change; add a funded PPMP instead
    $funded = app(CreatePpmp::class)($this->c->planner, $this->c->dept, $this->c->fy);
    $funded->items()->create(['item_id' => $this->paper->id, 'description' => 'Paper (trust fund)', 'unit_of_measure_id' => $this->uom->id,
        'quantity' => 5, 'estimated_unit_cost' => '250.00', 'proposed_mode' => ProcurementMode::SmallValueProcurement, 'fund_source_id' => $fund->id]);
    $this->plans->submit($this->c->planner, $funded);
    $this->plans->decide($this->c->head, $funded, ApprovalDecision::Approved);

    $v1 = app(CreateApp::class)($this->c->appPlanner, $this->c->fy);
    ($this->consolidate)($this->c->appPlanner, $v1);

    expect($v1->items()->count())->toBe(3)
        ->and($v1->items()->where('fund_source_id', $fund->id)->value('estimated_budget'))->toBe('1250.00');
});
