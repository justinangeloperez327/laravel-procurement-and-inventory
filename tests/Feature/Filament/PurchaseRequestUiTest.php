<?php

use App\Domain\Requests\PurchaseRequestWorkflow;
use App\Enums\PurchaseRequestStatus;
use App\Filament\Resources\BudgetAllocations\Pages\ViewBudgetAllocation;
use App\Filament\Resources\BudgetAllocations\RelationManagers\ReservationsRelationManager;
use App\Filament\Resources\PurchaseRequests\Pages\ListPurchaseRequests;
use App\Filament\Resources\PurchaseRequests\Pages\ViewPurchaseRequest;
use App\Filament\Resources\PurchaseRequests\RelationManagers\ItemsRelationManager;
use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->c = requestContext();
    $this->flow = app(PurchaseRequestWorkflow::class);
});

it('creates a request, adds lines and submits it from the screens', function () {
    $this->actingAs($this->c->requester);

    Livewire::test(ListPurchaseRequests::class)
        ->callAction('newRequest', ['department_id' => $this->c->dept->id, 'fiscal_year_id' => $this->c->fy->id,
            'app_item_id' => $this->c->appItem->id, 'purpose' => 'Supplies for the training'])
        ->assertNotified('Purchase request created');
    $pr = PurchaseRequest::sole();

    Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $pr, 'pageClass' => ViewPurchaseRequest::class])
        ->callAction(TestAction::make('create')->table(), [
            'description' => 'Ballpen, black', 'unit_of_measure_id' => $this->c->uom->id, 'quantity' => 200, 'estimated_unit_cost' => '12.50',
        ])
        ->assertHasNoFormErrors();

    Livewire::test(ViewPurchaseRequest::class, ['record' => $pr->id])
        ->assertActionHidden('approve')
        ->callAction('submit')
        ->assertNotified('Submitted for approval');

    expect($pr->fresh()->status)->toBe(PurchaseRequestStatus::DepartmentReview)->and($pr->fresh()->total_amount)->toBe('2500.00');
    $this->get("/admin/purchase-requests/{$pr->id}/edit")->assertForbidden();
});

it('shows each role only the action for its step', function () {
    $pr = draftRequest($this->c);
    $this->flow->submit($this->c->requester, $pr);

    $this->actingAs($this->c->budget);
    Livewire::test(ViewPurchaseRequest::class, ['record' => $pr->id])->assertActionHidden('approve')->assertActionHidden('certifyFunds');

    $this->actingAs($this->c->head);
    Livewire::test(ViewPurchaseRequest::class, ['record' => $pr->id])
        ->assertActionHidden('certifyFunds')
        ->callAction('approve')
        ->assertNotified('Approval recorded');

    $this->actingAs($this->c->budget);
    Livewire::test(ViewPurchaseRequest::class, ['record' => $pr->id])
        ->assertActionHidden('approve')
        ->callAction('certifyFunds', ['allocation_id' => $this->c->allocation->id])
        ->assertNotified('Funds certified and reserved');

    expect($pr->fresh()->status)->toBe(PurchaseRequestStatus::Approved);
    $this->get("/admin/purchase-requests/{$pr->id}")->assertSee('₱25,000.00 from '.$this->c->allocation->reference_no);
    $this->get("/admin/budget-allocations/{$this->c->allocation->id}")->assertOk();
    Livewire::test(ReservationsRelationManager::class, [
        'ownerRecord' => $this->c->allocation, 'pageClass' => ViewBudgetAllocation::class,
    ])->assertCanSeeTableRecords($pr->reservations);
});

it('scopes the list by department', function () {
    $mine = draftRequest($this->c);
    $dept2 = Department::factory()->for($this->c->agency)->create();
    $other = User::factory()->forAgency($this->c->agency)->create();
    grant($other, 'end_user', $dept2);

    $this->actingAs($other);
    Livewire::test(ListPurchaseRequests::class)->assertCanNotSeeTableRecords([$mine]);
    $this->get("/admin/purchase-requests/{$mine->id}")->assertNotFound();

    $this->actingAs($this->c->budget);
    Livewire::test(ListPurchaseRequests::class)->assertCanSeeTableRecords([$mine]);
});

it('lets the requester cancel and releases funds', function () {
    $pr = draftRequest($this->c);
    $this->flow->submit($this->c->requester, $pr);
    $this->flow->approve($this->c->head, $pr);
    $this->flow->certifyFunds($this->c->budget, $pr, $this->c->allocation);

    $this->actingAs($this->c->requester);
    Livewire::test(ViewPurchaseRequest::class, ['record' => $pr->id])
        ->callAction('cancel', ['reason' => 'Event postponed'])
        ->assertNotified('Request cancelled');

    expect($pr->fresh()->status)->toBe(PurchaseRequestStatus::Cancelled)->and($pr->reservations()->sole()->status)->toBe('released');
});
