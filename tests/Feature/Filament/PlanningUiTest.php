<?php

use App\Domain\Budget\ApproveBudgetAllocation;
use App\Domain\Budget\CreateBudgetAllocation as CreateAllocation;
use App\Domain\Planning\CreatePpmp;
use App\Domain\Planning\PlanApproval;
use App\Enums\ApprovalDecision;
use App\Enums\PlanStatus;
use App\Enums\ProcurementMode;
use App\Filament\Resources\AppVersions\Pages\ListAppVersions;
use App\Filament\Resources\AppVersions\Pages\ViewAppVersion;
use App\Filament\Resources\BudgetAllocations\Pages\CreateBudgetAllocation;
use App\Filament\Resources\BudgetAllocations\Pages\ViewBudgetAllocation;
use App\Filament\Resources\MarketScopings\Pages\ListMarketScopings;
use App\Filament\Resources\Ppmps\Pages\ListPpmps;
use App\Filament\Resources\Ppmps\Pages\ViewPpmp;
use App\Filament\Resources\Ppmps\RelationManagers\ItemsRelationManager;
use App\Models\AccountCode;
use App\Models\AppVersion;
use App\Models\BudgetAllocation;
use App\Models\Department;
use App\Models\FundSource;
use App\Models\MarketScoping;
use App\Models\Ppmp;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->c = planningContext();
    $this->uom = UnitOfMeasure::factory()->create();
    $this->ppmpWithItem = function (): Ppmp {
        $ppmp = app(CreatePpmp::class)($this->c->planner, $this->c->dept, $this->c->fy);
        $ppmp->items()->create(['description' => 'Toner', 'unit_of_measure_id' => $this->uom->id, 'quantity' => 4,
            'estimated_unit_cost' => '3500.00', 'proposed_mode' => ProcurementMode::SmallValueProcurement]);

        return $ppmp;
    };
});

it('renders the planning and budget pages for the right people', function () {
    $ppmp = ($this->ppmpWithItem)();

    $this->actingAs($this->c->planner);
    $this->get('/admin/ppmps')->assertOk();
    $this->get("/admin/ppmps/{$ppmp->id}")->assertOk();
    $this->get("/admin/ppmps/{$ppmp->id}/edit")->assertOk();
    $this->get('/admin/market-scopings')->assertOk();
    $this->get('/admin/app')->assertOk();
    $this->get('/admin/budget-allocations')->assertForbidden();

    $this->actingAs($this->c->budget);
    $this->get('/admin/budget-allocations')->assertOk();
    $this->get('/admin/budget-allocations/create')->assertOk();
});

it('creates a PPMP, adds lines and submits it from the screens', function () {
    $this->actingAs($this->c->planner);

    Livewire::test(ListPpmps::class)
        ->callAction('newPpmp', ['department_id' => $this->c->dept->id, 'fiscal_year_id' => $this->c->fy->id])
        ->assertNotified('PPMP created');
    $ppmp = Ppmp::sole();

    Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $ppmp, 'pageClass' => ViewPpmp::class])
        ->callAction(TestAction::make('create')->table(), [
            'description' => 'Bond paper, A4', 'unit_of_measure_id' => $this->uom->id, 'quantity' => 10,
            'estimated_unit_cost' => '245.50', 'proposed_mode' => ProcurementMode::SmallValueProcurement->value,
        ])
        ->assertHasNoFormErrors();

    Livewire::test(ViewPpmp::class, ['record' => $ppmp->id])
        ->assertActionHidden('approve')
        ->callAction('submit')
        ->assertNotified('Submitted for approval');

    expect($ppmp->fresh()->status)->toBe(PlanStatus::Submitted)->and($ppmp->fresh()->total_amount)->toBe('2455.00');

    Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $ppmp->fresh(), 'pageClass' => ViewPpmp::class])
        ->assertActionHidden(TestAction::make('create')->table());
    $this->get("/admin/ppmps/{$ppmp->id}/edit")->assertForbidden();
});

it('lets the department head approve or return', function () {
    $ppmp = ($this->ppmpWithItem)();
    app(PlanApproval::class)->submit($this->c->planner, $ppmp);

    $this->actingAs($this->c->head);
    Livewire::test(ViewPpmp::class, ['record' => $ppmp->id])
        ->assertActionVisible('approve')
        ->callAction('return', ['remarks' => 'Add delivery schedule'])
        ->assertNotified('Returned to the preparer');

    expect($ppmp->fresh()->status)->toBe(PlanStatus::Returned);
});

it('shows each department only its own plans', function () {
    $mine = ($this->ppmpWithItem)();
    $dept2 = Department::factory()->for($this->c->agency)->create();
    $planner2 = User::factory()->forAgency($this->c->agency)->create();
    grant($planner2, 'planning_officer', $dept2);
    $theirs = app(CreatePpmp::class)($planner2, $dept2, $this->c->fy);

    $this->actingAs($planner2);
    Livewire::test(ListPpmps::class)->assertCanSeeTableRecords([$theirs])->assertCanNotSeeTableRecords([$mine]);
    $this->get("/admin/ppmps/{$mine->id}")->assertNotFound();

    $this->actingAs($this->c->appPlanner);
    Livewire::test(ListPpmps::class)->assertCanSeeTableRecords([$mine, $theirs]);
});

it('runs market scoping creation from the list', function () {
    $this->actingAs($this->c->planner);

    Livewire::test(ListMarketScopings::class)
        ->callAction('newMarketScoping', ['department_id' => $this->c->dept->id, 'fiscal_year_id' => $this->c->fy->id,
            'title' => 'ICT canvass', 'conducted_on' => now()->toDateString()])
        ->assertNotified('Market scoping created');

    expect(MarketScoping::sole()->conducted_by)->toBe($this->c->planner->id);
});

it('opens, consolidates and approves the APP from the screens', function () {
    $ppmp = ($this->ppmpWithItem)();
    app(PlanApproval::class)->submit($this->c->planner, $ppmp);
    app(PlanApproval::class)->decide($this->c->head, $ppmp, ApprovalDecision::Approved);

    $this->actingAs($this->c->appPlanner);
    Livewire::test(ListAppVersions::class)->callAction('openApp', ['fiscal_year_id' => $this->c->fy->id])->assertNotified('APP opened');
    $version = AppVersion::sole();

    Livewire::test(ViewAppVersion::class, ['record' => $version->id])
        ->callAction('consolidate')
        ->assertNotified('Consolidated')
        ->callAction('submit')
        ->assertNotified('Submitted for approval');
    $this->get("/admin/app/{$version->id}")->assertOk();

    $this->actingAs($this->c->hope);
    Livewire::test(ViewAppVersion::class, ['record' => $version->id])->callAction('approve')->assertNotified('Approval recorded');

    expect($version->fresh()->status)->toBe(PlanStatus::Approved)->and($version->fresh()->total_amount)->toBe('14000.00');
});

it('records, approves and adjusts allotments from the screens', function () {
    $fund = FundSource::factory()->create(['agency_id' => $this->c->agency->id]);
    $account = AccountCode::factory()->create();

    $this->actingAs($this->c->budget);
    Livewire::test(CreateBudgetAllocation::class)
        ->fillForm(['fiscal_year_id' => $this->c->fy->id, 'fund_source_id' => $fund->id, 'account_code_id' => $account->id,
            'amount' => '500000', 'authorization_reference' => 'SARO-2027-0101'])
        ->call('create')
        ->assertHasNoFormErrors();
    $allocation = BudgetAllocation::sole();
    expect($allocation->recorded_by)->toBe($this->c->budget->id)->and($allocation->amount)->toBe('500000.00');

    Livewire::test(ViewBudgetAllocation::class, ['record' => $allocation->id])->assertActionHidden('approve');

    $this->actingAs($this->c->budget2);
    $view = fn () => Livewire::test(ViewBudgetAllocation::class, ['record' => $allocation->id]);
    $view()->callAction('approve')->assertNotified('Allotment approved');
    $view()->callAction('reduce', ['amount' => '600000', 'authorization' => 'x', 'reason' => 'y'])->assertNotified('Change not allowed');
    $view()->callAction('augment', ['amount' => '25000.50', 'authorization' => 'SARO-2', 'reason' => 'Supplemental'])->assertNotified('Augmentation recorded');
    $view()->assertSee('₱525,000.50');

    expect($allocation->adjustments()->count())->toBe(1);
});

it('hides allotment editing after approval', function () {
    $allocation = app(ApproveBudgetAllocation::class)($this->c->budget2, app(CreateAllocation::class)(
        $this->c->budget, $this->c->fy, FundSource::factory()->create(['agency_id' => $this->c->agency->id]), AccountCode::factory()->create(), '100', 'SARO'));

    $this->actingAs($this->c->budget)->get("/admin/budget-allocations/{$allocation->id}/edit")->assertForbidden();
});
