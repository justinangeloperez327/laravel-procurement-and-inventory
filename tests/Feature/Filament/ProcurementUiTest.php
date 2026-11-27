<?php

use App\Domain\Procurement\CreateProcurementProject;
use App\Domain\Procurement\ModeSelection;
use App\Enums\ProcurementCategory;
use App\Enums\ProcurementMode;
use App\Enums\ProcurementProjectStatus;
use App\Filament\Resources\BacCommittees\Pages\ViewBacCommittee;
use App\Filament\Resources\BacCommittees\RelationManagers\MeetingsRelationManager;
use App\Filament\Resources\ProcurementProjects\Pages\ListProcurementProjects;
use App\Filament\Resources\ProcurementProjects\Pages\ViewProcurementProject;
use App\Filament\Resources\ProcurementProjects\RelationManagers\DeadlinesRelationManager;
use App\Filament\Resources\ProcurementProjects\RelationManagers\JustificationsRelationManager;
use App\Models\BacResolution;
use App\Models\Meeting;
use App\Models\ProcurementProject;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->c = procurementContext();
    $this->project = fn () => app(CreateProcurementProject::class)($this->c->officer, $this->c->fy, 'Supplies', ProcurementCategory::Goods,
        [approvedRequest($this->c)->items()->value('id')]);
});

it('renders the procurement and BAC pages', function () {
    $project = ($this->project)();

    $this->actingAs($this->c->officer);
    $this->get('/admin/procurement-projects')->assertOk();
    $this->get("/admin/procurement-projects/{$project->id}")->assertOk()->assertSee($project->reference_no);
    $this->get('/admin/bac-committees')->assertOk();
    $this->get("/admin/bac-committees/{$this->c->committee->id}")->assertOk();

    $this->actingAs($this->c->requester)->get('/admin/procurement-projects')->assertForbidden();
});

it('creates a project from approved request lines', function () {
    $line = approvedRequest($this->c)->items()->value('id');
    $this->actingAs($this->c->officer);

    Livewire::test(ListProcurementProjects::class)
        ->callAction('newProject', ['fiscal_year_id' => $this->c->fy->id, 'title' => 'Paper', 'category' => 'goods', 'lines' => [$line]])
        ->assertNotified('Procurement project created');

    expect(ProcurementProject::sole()->approved_budget)->toBe('25000.00');
});

it('proposes a mode with live screening, and the BAC endorses it', function () {
    $project = ($this->project)();
    $this->actingAs($this->c->officer);

    Livewire::test(ViewProcurementProject::class, ['record' => $project->id])
        ->callAction('proposeMode', ['mode' => 'small_value_procurement', 'justification' => 'Low value', 'evidence' => ['canvass' => '3 quotations']])
        ->assertNotified('Mode proposed for BAC review');
    expect($project->fresh()->status)->toBe(ProcurementProjectStatus::ModeReview);

    // BAC meeting, attendance and resolution through the committee screens.
    $this->actingAs($this->c->secretariat);
    $meetings = fn () => Livewire::test(MeetingsRelationManager::class, ['ownerRecord' => $this->c->committee, 'pageClass' => ViewBacCommittee::class]);
    $meetings()->callAction(TestAction::make('schedule')->table(), ['type' => 'regular', 'scheduled_at' => now()->toDateTimeString(), 'project_id' => $project->id])
        ->assertNotified('Meeting scheduled');
    $meeting = Meeting::sole();
    $meetings()->callAction(TestAction::make('attendance')->table($meeting), [
        'held_at' => now()->toDateTimeString(), 'present' => [$this->c->chair->id, $this->c->member1->id], 'observers' => ['COA auditor'],
    ])->assertNotified('Attendance recorded');
    expect($meeting->fresh()->quorum_met)->toBeTrue();

    $this->actingAs($this->c->chair);
    $meetings()->callAction(TestAction::make('resolution')->table($meeting->fresh()), [
        'type' => 'recommend_mode', 'title' => 'Recommending SVP', 'project_id' => $project->id,
    ])->assertNotified('Resolution adopted');
    $resolution = BacResolution::sole();

    declareNoConflict($project, $this->c->chair, $this->c->hope);
    $proposal = $project->justifications()->sole();
    Livewire::test(JustificationsRelationManager::class, ['ownerRecord' => $project->fresh(), 'pageClass' => ViewProcurementProject::class])
        ->callAction(TestAction::make('bacReview')->table($proposal), ['decision' => 'endorsed', 'resolution_id' => $resolution->id])
        ->assertNotified('BAC review recorded');

    $this->actingAs($this->c->hope);
    Livewire::test(JustificationsRelationManager::class, ['ownerRecord' => $project->fresh(), 'pageClass' => ViewProcurementProject::class])
        ->callAction(TestAction::make('hopeDecision')->table($proposal->fresh()), ['decision' => 'approved'])
        ->assertNotified('Decision recorded');

    expect($project->fresh()->status)->toBe(ProcurementProjectStatus::Solicitation);
});

it('shows a refusal when the mode is ineligible', function () {
    $project = ($this->project)();
    $this->actingAs($this->c->officer);

    Livewire::test(ViewProcurementProject::class, ['record' => $project->id])
        ->callAction('proposeMode', ['mode' => 'negotiated_procurement', 'justification' => 'x'])
        ->assertNotified('Change not allowed');
    expect($project->fresh()->status)->toBe(ProcurementProjectStatus::Proposed);
});

it('hides BAC review from reviewers who have not declared', function () {
    $project = ($this->project)();
    $proposal = app(ModeSelection::class)->propose($this->c->officer, $project, ProcurementMode::CompetitiveBidding, 'Default');

    $this->actingAs($this->c->chair);
    Livewire::test(JustificationsRelationManager::class, ['ownerRecord' => $project->fresh(), 'pageClass' => ViewProcurementProject::class])
        ->assertActionHidden(TestAction::make('bacReview')->table($proposal));
});

it('sets and extends deadlines from the screen', function () {
    $project = ($this->project)();
    $this->actingAs($this->c->officer);
    $rm = fn () => Livewire::test(DeadlinesRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProcurementProject::class]);

    $rm()->callAction(TestAction::make('setDeadline')->table(), ['type' => 'bid_submission', 'due_at' => now()->addWeek()->toDateTimeString()])
        ->assertNotified('Deadline set');
    $rm()->callAction(TestAction::make('extend')->table($project->deadlines()->sole()), ['due_at' => now()->addWeeks(2)->toDateTimeString(), 'reason' => 'Bid bulletin 1'])
        ->assertNotified('Deadline extended');

    expect($project->deadlines()->count())->toBe(2);
});
