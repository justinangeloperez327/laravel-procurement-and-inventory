<?php

use App\Domain\Bac\BacMeetings;
use App\Enums\ProcurementMode;
use App\Enums\ProcurementProjectStatus;
use App\Filament\Resources\ProcurementProjects\Pages\ViewProcurementProject;
use App\Filament\Resources\ProcurementProjects\RelationManagers\BiddingsRelationManager;
use App\Filament\Resources\ProcurementProjects\RelationManagers\BidsRelationManager;
use App\Filament\Resources\ProcurementProjects\RelationManagers\RfqsRelationManager;
use App\Models\Bid;
use App\Models\Bidding;
use App\Models\BidEvaluation;
use App\Models\Quotation;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->c = biddingContext();
});

it('runs competitive bidding from receipt to evaluation on the screens', function () {
    $project = projectInSolicitation($this->c, ProcurementMode::CompetitiveBidding);
    $this->actingAs($this->c->officer);
    $biddings = fn () => Livewire::test(BiddingsRelationManager::class, ['ownerRecord' => $project->fresh(), 'pageClass' => ViewProcurementProject::class]);

    $biddings()->callAction(TestAction::make('openBidding')->table(), [
        'deadline' => now()->addDays(7)->setTime(9, 0)->toDateTimeString(), 'opening' => now()->addDays(7)->setTime(10, 0)->toDateTimeString(),
    ])->assertNotified('Bidding opened');
    $bidding = Bidding::sole();

    $this->actingAs($this->c->secretariat);
    $biddings()->callAction(TestAction::make('receiveBid')->table($bidding), [
        'supplier_id' => $this->c->suppliers[0]->id,
        'financial' => UploadedFile::fake()->create('offer.pdf', 10, 'application/pdf'),
    ])->assertNotified('Bid received');
    $bid = Bid::sole();

    Livewire::test(BidsRelationManager::class, ['ownerRecord' => $project->fresh(), 'pageClass' => ViewProcurementProject::class])
        ->assertCanSeeTableRecords([$bid])->assertSee('Sealed');

    $this->travelTo(now()->addDays(7)->setTime(10, 5));
    $meetings = app(BacMeetings::class);
    $meeting = $meetings->recordHeld($this->c->secretariat, $meetings->schedule($this->c->secretariat, $this->c->committee, 'bid_opening', now()->toDateTimeString(), $project),
        [$this->c->chair->id, $this->c->member1->id]);

    $this->actingAs($this->c->chair);
    $biddings()->callAction(TestAction::make('openBids')->table($bidding), ['meeting_id' => $meeting->id, 'amounts' => [$bid->id => '21000']])
        ->assertNotified('Bids opened');
    expect($bid->fresh()->bid_amount)->toBe('21000.00')->and($project->fresh()->status)->toBe(ProcurementProjectStatus::Evaluation);

    $this->actingAs($this->c->member1);
    Livewire::test(ViewProcurementProject::class, ['record' => $project->id])
        ->callAction('evaluate', ['offer' => "bid:{$bid->id}", 'criterion_id' => $project->criteria()->first()->id, 'result' => 'pass'])
        ->assertNotified('Evaluation recorded');
    expect(BidEvaluation::count())->toBe(1);
    $this->get("/admin/procurement-projects/{$project->id}")->assertOk()->assertSee('Alpha Trading');
});

it('shows RFQs for alternative modes and records quotations', function () {
    $project = projectInSolicitation($this->c, ProcurementMode::SmallValueProcurement);
    $this->actingAs($this->c->officer);
    $rfqs = fn () => Livewire::test(RfqsRelationManager::class, ['ownerRecord' => $project->fresh(), 'pageClass' => ViewProcurementProject::class]);

    $rfqs()->callAction(TestAction::make('issueRfq')->table(), ['deadline' => now()->addDays(3)->toDateTimeString(), 'minimum' => 3])
        ->assertNotified('RFQ issued');
    $rfq = $project->rfqs()->sole();
    $line = $project->items()->sole();

    $rfqs()->callAction(TestAction::make('recordQuotation')->table($rfq), ['supplier_id' => $this->c->suppliers[1]->id, 'prices' => [$line->id => '240']])
        ->assertNotified('Quotation recorded');
    expect(Quotation::sole()->total_amount)->toBe('24000.00');

    expect(BiddingsRelationManager::canViewForRecord($project->fresh(), ViewProcurementProject::class))->toBeFalse();
});
