<?php

use App\Domain\Bac\BacMeetings;
use App\Domain\Compliance\ConflictDeclarations;
use App\Domain\Procurement\CreateProcurementProject;
use App\Domain\Procurement\ModeScreening;
use App\Domain\Procurement\ModeSelection;
use App\Domain\Procurement\ProjectSchedule;
use App\Domain\Regulatory\RetireRuleVersion;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\ApprovalDecision;
use App\Enums\ProcurementCategory;
use App\Enums\ProcurementMode;
use App\Enums\ProcurementProjectStatus;
use App\Models\ComplianceFlag;
use App\Models\ProcurementProject;

beforeEach(function () {
    $this->c = procurementContext();
    $this->modes = app(ModeSelection::class);
    $this->project = fn (string $qty = '100', string $cost = '250.00') => app(CreateProcurementProject::class)(
        $this->c->officer, $this->c->fy, 'Office supplies', ProcurementCategory::Goods, [approvedRequest($this->c, $qty, $cost)->items()->value('id')],
    );
    $this->recommend = function (ProcurementProject $project) {
        $meeting = quorateMeeting($this->c, $project);

        return app(BacMeetings::class)->adoptResolution($this->c->chair, $meeting, 'recommend_mode', 'Recommending SVP for '.$project->reference_no);
    };
});

it('runs competitive bidding through BAC review straight to solicitation', function () {
    $project = ($this->project)();
    $proposal = $this->modes->propose($this->c->officer, $project, ProcurementMode::CompetitiveBidding, 'Default method');
    expect($project->fresh()->status)->toBe(ProcurementProjectStatus::ModeReview);

    declareNoConflict($project, $this->c->chair);
    $this->modes->bacReview($this->c->chair, $proposal, ApprovalDecision::Endorsed);

    expect($proposal->fresh()->status)->toBe('approved')
        ->and($project->fresh()->status)->toBe(ProcurementProjectStatus::Solicitation)
        ->and($project->fresh()->procurementMode->code)->toBe(ProcurementMode::CompetitiveBidding);
});

it('screens alternative modes against approved rules and refuses ineligible ones (docs/16)', function () {
    $tooBig = ($this->project)('300', '250.00'); // ₱75,000 > ₱50,000 ceiling

    expect(fn () => $this->modes->propose($this->c->officer, $tooBig, ProcurementMode::SmallValueProcurement, 'Urgent', ['canvass' => 'Three quotations']))
        ->toThrow(WorkflowViolation::class, 'must not exceed ₱50,000.00');
    expect($tooBig->fresh()->status)->toBe(ProcurementProjectStatus::Proposed);
});

it('requires the evidence named in the rules', function () {
    $this->modes->propose($this->c->officer, ($this->project)(), ProcurementMode::SmallValueProcurement, 'Low value');
})->throws(WorkflowViolation::class, 'Missing evidence: canvass');

it('refuses alternative modes that have no approved rules', function () {
    $this->modes->propose($this->c->officer, ($this->project)(), ProcurementMode::NegotiatedProcurement, 'Emergency');
})->throws(WorkflowViolation::class, 'no approved screening rules');

it('ignores retired rule versions', function () {
    app(RetireRuleVersion::class)($this->c->lawyer, $this->c->svpRule, 'Superseded');

    $this->modes->propose($this->c->officer, ($this->project)(), ProcurementMode::SmallValueProcurement, 'x', ['canvass' => 'y']);
})->throws(WorkflowViolation::class, 'No approved version of SVP-GOODS');

it('runs an alternative mode through BAC resolution and HoPE approval', function () {
    $project = ($this->project)();
    $proposal = $this->modes->propose($this->c->officer, $project, ProcurementMode::SmallValueProcurement, 'ABC below the SVP ceiling', ['canvass' => 'Canvass of 3 suppliers']);
    expect($proposal->screening_result['checks'])->toHaveCount(3)
        ->and($proposal->regulatory_rule_version_id)->toBe($this->c->svpRule->id);

    declareNoConflict($project, $this->c->chair, $this->c->hope);
    expect(fn () => $this->modes->bacReview($this->c->chair, $proposal, ApprovalDecision::Endorsed))->toThrow(WorkflowViolation::class, 'BAC resolution');

    $resolution = ($this->recommend)($project);
    $this->modes->bacReview($this->c->chair, $proposal, ApprovalDecision::Endorsed, 'Recommended', $resolution);
    expect($proposal->fresh()->status)->toBe('endorsed')->and($project->fresh()->status)->toBe(ProcurementProjectStatus::ModeReview);

    $this->modes->hopeDecision($this->c->hope, $proposal->fresh(), ApprovalDecision::Approved);
    expect($project->fresh()->status)->toBe(ProcurementProjectStatus::Solicitation)
        ->and($project->fresh()->procurementMode->code)->toBe(ProcurementMode::SmallValueProcurement);
});

it('requires reviewers to declare and to have no conflict', function () {
    $project = ($this->project)();
    $proposal = $this->modes->propose($this->c->officer, $project, ProcurementMode::CompetitiveBidding, 'Default');

    expect(fn () => $this->modes->bacReview($this->c->chair, $proposal, ApprovalDecision::Endorsed))->toThrow(WorkflowViolation::class, 'Declare');

    app(ConflictDeclarations::class)->declare($this->c->chair, $project, null, true, 'Relative is a likely bidder', 'recused');
    expect(fn () => $this->modes->bacReview($this->c->chair, $proposal, ApprovalDecision::Endorsed))->toThrow(WorkflowViolation::class, 'recuse');

    declareNoConflict($project, $this->c->member1);
    $this->modes->bacReview($this->c->member1, $proposal, ApprovalDecision::Endorsed);
    expect($project->fresh()->status)->toBe(ProcurementProjectStatus::Solicitation);
});

it('does not let the preparer review the proposal', function () {
    $project = ($this->project)();
    $proposal = $this->modes->propose($this->c->secretariat, $project, ProcurementMode::CompetitiveBidding, 'Default');
    grant($this->c->secretariat, 'bac_member');
    declareNoConflict($project, $this->c->secretariat);

    $this->modes->bacReview($this->c->secretariat, $proposal, ApprovalDecision::Endorsed);
})->throws(WorkflowViolation::class, 'preparer');

it('returns a proposal so another mode can be proposed', function () {
    $project = ($this->project)();
    $proposal = $this->modes->propose($this->c->officer, $project, ProcurementMode::SmallValueProcurement, 'x', ['canvass' => 'y']);
    declareNoConflict($project, $this->c->chair);

    $this->modes->bacReview($this->c->chair, $proposal, ApprovalDecision::Returned, 'Use competitive bidding; requirement recurs');
    expect($project->fresh()->status)->toBe(ProcurementProjectStatus::Proposed)->and($proposal->fresh()->status)->toBe('returned');

    $again = $this->modes->propose($this->c->officer, $project->fresh(), ProcurementMode::CompetitiveBidding, 'As advised');
    expect($again->status)->toBe('proposed');
});

it('freezes the project scope once mode review starts', function () {
    $project = ($this->project)();
    $this->modes->propose($this->c->officer, $project, ProcurementMode::CompetitiveBidding, 'Default');

    $project->fresh()->update(['title' => 'Changed']);
})->throws(LogicException::class);

it('flags related sub-threshold purchases for splitting review (L-05)', function () {
    $first = ($this->project)('120', '250.00'); // ₱30,000
    $this->modes->propose($this->c->officer, $first, ProcurementMode::SmallValueProcurement, 'x', ['canvass' => 'y']);
    expect(ComplianceFlag::where('flag_type', 'possible_splitting')->count())->toBe(0);

    $second = ($this->project)('120', '250.00'); // another ₱30,000 of the same category
    $this->modes->propose($this->c->officer, $second, ProcurementMode::SmallValueProcurement, 'x', ['canvass' => 'y']);

    $flag = ComplianceFlag::where('flag_type', 'possible_splitting')->sole();
    expect($flag->subject_id)->toBe($second->id)
        ->and($flag->description)->toContain($first->reference_no)->toContain('₱60,000.00')->toContain('₱50,000.00');
});

it('only lets the HoPE cancel once solicitation has started', function () {
    $project = ($this->project)();
    $proposal = $this->modes->propose($this->c->officer, $project, ProcurementMode::CompetitiveBidding, 'Default');
    declareNoConflict($project, $this->c->chair);
    $this->modes->bacReview($this->c->chair, $proposal, ApprovalDecision::Endorsed);

    expect(fn () => app(ProjectSchedule::class)->cancel($this->c->officer, $project->fresh(), 'x'))->toThrow(WorkflowViolation::class, 'procurement_mode.approve');
    app(ProjectSchedule::class)->cancel($this->c->hope, $project->fresh(), 'Funds realigned by DBM');
    expect($project->fresh()->status)->toBe(ProcurementProjectStatus::Cancelled);
});

it('applies the rules in effect when procurement started', function () {
    $project = ($this->project)();
    $result = app(ModeScreening::class)->screen($project, ProcurementMode::SmallValueProcurement, ['canvass' => 'y'], now()->setDate(2019, 6, 1));

    expect($result->eligible())->toBeFalse()->and($result->blockingFailures()[0]['message'])->toContain('2019-06-01');
});
