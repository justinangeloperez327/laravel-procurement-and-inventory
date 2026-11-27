<?php

use App\Enums\ApprovalDecision;
use App\Enums\RuleStatus;
use App\Filament\Resources\ApprovalWorkflows\Pages\EditApprovalWorkflow;
use App\Filament\Resources\ApprovalWorkflows\RelationManagers\StepsRelationManager;
use App\Filament\Resources\Items\Pages\ManageItems;
use App\Filament\Resources\RegulatoryRuleVersions\Pages\CreateRegulatoryRuleVersion;
use App\Filament\Resources\RegulatoryRuleVersions\Pages\ListRegulatoryRuleVersions;
use App\Models\Agency;
use App\Models\ApprovalAction;
use App\Models\ApprovalWorkflow;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\RegulatoryRuleVersion;
use App\Models\RegulatorySource;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    seedRoles();
    $this->agency = Agency::factory()->create();
    $this->admin = adminFor($this->agency);
});

dataset('phase 2 pages', [
    '/admin/fund-sources', '/admin/account-codes', '/admin/units-of-measure', '/admin/item-categories', '/admin/items',
    '/admin/warehouses', '/admin/warehouses/create', '/admin/regulatory-sources', '/admin/regulatory-rule-versions',
    '/admin/regulatory-rule-versions/create', '/admin/approval-workflows', '/admin/approval-workflows/create',
]);

it('renders every phase 2 page for administrators', function (string $url) {
    $this->actingAs($this->admin)->get($url)->assertOk();
})->with('phase 2 pages');

it('renders edit and view pages', function () {
    $this->actingAs($this->admin);
    $warehouse = Warehouse::factory()->create(['agency_id' => $this->agency->id]);
    $workflow = ApprovalWorkflow::factory()->create(['agency_id' => $this->agency->id]);
    $rule = RegulatoryRuleVersion::factory()->create();

    $this->get("/admin/warehouses/{$warehouse->id}/edit")->assertOk();
    $this->get("/admin/approval-workflows/{$workflow->id}/edit")->assertOk();
    $this->get("/admin/regulatory-rule-versions/{$rule->id}")->assertOk();
    $this->get("/admin/regulatory-rule-versions/{$rule->id}/edit")->assertOk();
});

it('lets catalog managers maintain only their own agency items', function () {
    $supply = User::factory()->forAgency($this->agency)->create();
    grant($supply, 'supply_officer');
    $own = Item::factory()->create(['agency_id' => $this->agency->id]);
    $foreign = Item::factory()->create();

    $this->actingAs($supply);
    $this->get('/admin/items')->assertOk();
    $this->get('/admin/fund-sources')->assertForbidden();

    Livewire::test(ManageItems::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$foreign])
        ->callAction('create', [
            'agency_id' => $this->agency->id,
            'code' => 'ITM-NEW-1',
            'name' => 'Bond paper, A4',
            'item_category_id' => ItemCategory::factory()->create()->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create()->id,
            'asset_class' => 'consumable',
        ])
        ->assertHasNoFormErrors();

    expect(Item::where('code', 'ITM-NEW-1')->value('agency_id'))->toBe($this->agency->id)
        ->and($supply->can('update', $foreign))->toBeFalse();
});

it('rejects an item for another agency even if the form is tampered with', function () {
    $supply = User::factory()->forAgency($this->agency)->create();
    grant($supply, 'supply_officer');
    $this->actingAs($supply);

    Livewire::test(ManageItems::class)
        ->callAction('create', [
            'agency_id' => Agency::factory()->create()->id,
            'code' => 'ITM-X', 'name' => 'X',
            'item_category_id' => ItemCategory::factory()->create()->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create()->id,
            'asset_class' => 'consumable',
        ])
        ->assertHasFormErrors(['agency_id']);
});

it('records the preparer and lets only another legal reviewer approve', function () {
    $officer = User::factory()->forAgency($this->agency)->create();
    grant($officer, 'procurement_officer');
    $reviewer = User::factory()->forAgency($this->agency)->create();
    grant($reviewer, 'legal_reviewer');

    $this->actingAs($officer);
    Livewire::test(CreateRegulatoryRuleVersion::class)
        ->fillForm([
            'regulatory_source_id' => RegulatorySource::factory()->create()->id,
            'rule_code' => 'RO-LIMIT', 'version' => 1, 'provision' => 'Repeat order',
            'legal_reference' => 'IRR Sec. Y', 'test_reference' => 'tests/Feature/RepeatOrder',
            'approved_interpretation' => 'Text', 'effective_from' => '2025-01-01',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rule = RegulatoryRuleVersion::where('rule_code', 'RO-LIMIT')->sole();
    expect($rule->prepared_by)->toBe($officer->id)->and($rule->status)->toBe(RuleStatus::Draft);

    Livewire::test(ListRegulatoryRuleVersions::class)->assertActionHidden(TestAction::make('approve')->table($rule));

    $this->actingAs($reviewer);
    Livewire::test(ListRegulatoryRuleVersions::class)
        ->callAction(TestAction::make('approve')->table($rule))
        ->assertNotified('Rule version approved');

    expect($rule->fresh()->status)->toBe(RuleStatus::Approved)
        ->and($reviewer->can('update', $rule->fresh()))->toBeFalse();
});

it('freezes approval workflows once they have been used', function () {
    $this->actingAs($this->admin);
    $workflow = ApprovalWorkflow::factory()->create(['agency_id' => $this->agency->id]);
    $step = $workflow->steps()->create(['sequence' => 1, 'name' => 'Head', 'permission_code' => 'purchase_request.approve', 'department_scoped' => true, 'is_required' => true]);

    $component = fn () => Livewire::test(StepsRelationManager::class, ['ownerRecord' => $workflow, 'pageClass' => EditApprovalWorkflow::class]);
    $component()->assertActionVisible(TestAction::make('create')->table());

    ApprovalAction::factory()->create(['approval_step_id' => $step->id, 'decision' => ApprovalDecision::Approved]);

    $component()->assertActionHidden(TestAction::make('create')->table());
    expect($this->admin->can('update', $workflow))->toBeFalse();
});

it('hides workflow configuration from non-administrators', function () {
    $head = User::factory()->forAgency($this->agency)->create();
    grant($head, 'department_head');

    $this->actingAs($head)->get('/admin/approval-workflows')->assertForbidden();
});
