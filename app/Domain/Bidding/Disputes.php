<?php

namespace App\Domain\Bidding;

use App\Domain\Shared\WorkflowViolation;
use App\Models\AuditLog;
use App\Models\BacResolution;
use App\Models\Bid;
use App\Models\Bidding;
use App\Models\ProcurementProject;
use App\Models\Protest;
use App\Models\Quotation;
use App\Models\ReconsiderationRequest;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\User;

/**
 * Requests for reconsideration (decided by the BAC) and protests (decided by the HoPE) (L-12).
 * A pending protest blocks award (docs/16 "Pending award-blocking protest → stop award"); phase 8
 * checks blocksAward() before any award.
 *
 * A protest requires a prior request for reconsideration that the BAC denied; confirm this sequence
 * and the protest fee rules against the current IRR.
 */
class Disputes
{
    public const OPEN = ['filed', 'under_review'];

    public function fileReconsideration(User $actor, ProcurementProject $project, Supplier $supplier, string $grounds, ?BacResolution $resolution = null): ReconsiderationRequest
    {
        BiddingGuard::require($actor, 'rfq.manage', $project);
        $this->assertParticipant($project, $supplier);
        if (blank($grounds)) {
            throw new WorkflowViolation('State the grounds for reconsideration.');
        }

        $request = ReconsiderationRequest::create([
            'procurement_project_id' => $project->id, 'supplier_id' => $supplier->id, 'bac_resolution_id' => $resolution?->id,
            'filed_at' => now(), 'grounds' => $grounds, 'status' => 'filed', 'recorded_by' => $actor->id,
        ]);
        AuditLog::record('reconsideration.filed', $request, null, ['supplier' => $supplier->name]);

        return $request;
    }

    public function decideReconsideration(User $actor, ReconsiderationRequest $request, bool $granted, string $decision): ReconsiderationRequest
    {
        BiddingGuard::require($actor, 'bac.recommend', $request->procurementProject);
        $this->assertOpen($request->status);
        if (blank($decision)) {
            throw new WorkflowViolation('Record the decision.');
        }
        $request->forceFill(['status' => $granted ? 'granted' : 'denied', 'decision' => $decision, 'decided_at' => now(), 'decided_by' => $actor->id])->save();
        AuditLog::record('reconsideration.'.($granted ? 'granted' : 'denied'), $request);

        return $request;
    }

    public function fileProtest(User $actor, ProcurementProject $project, Supplier $supplier, string $grounds, ?string $fee = null): Protest
    {
        BiddingGuard::require($actor, 'rfq.manage', $project);
        $this->assertParticipant($project, $supplier);
        if (blank($grounds)) {
            throw new WorkflowViolation('State the grounds of the protest.');
        }
        $denied = ReconsiderationRequest::where('procurement_project_id', $project->id)->where('supplier_id', $supplier->id)->where('status', 'denied')->exists();
        if (! $denied) {
            throw new WorkflowViolation('A protest needs a prior request for reconsideration that the BAC denied.');
        }

        $protest = Protest::create([
            'procurement_project_id' => $project->id, 'supplier_id' => $supplier->id, 'filed_at' => now(), 'grounds' => $grounds,
            'protest_fee' => $fee, 'status' => 'filed', 'blocks_award' => true, 'recorded_by' => $actor->id,
        ]);
        AuditLog::record('protest.filed', $protest, null, ['supplier' => $supplier->name]);

        return $protest;
    }

    public function decideProtest(User $hope, Protest $protest, bool $granted, string $decision): Protest
    {
        BiddingGuard::require($hope, 'award.approve', $protest->procurementProject);
        $this->assertOpen($protest->status);
        if (blank($decision)) {
            throw new WorkflowViolation('Record the decision.');
        }
        $protest->forceFill(['status' => $granted ? 'granted' : 'denied', 'decision' => $decision, 'decided_at' => now(), 'decided_by' => $hope->id])->save();
        AuditLog::record('protest.'.($granted ? 'granted' : 'denied'), $protest);

        return $protest;
    }

    public static function blocksAward(ProcurementProject $project): bool
    {
        return Protest::where('procurement_project_id', $project->id)->where('blocks_award', true)->whereIn('status', self::OPEN)->exists();
    }

    private function assertOpen(string $status): void
    {
        if (! in_array($status, self::OPEN, true)) {
            throw new WorkflowViolation("This has already been decided ({$status}).");
        }
    }

    private function assertParticipant(ProcurementProject $project, Supplier $supplier): void
    {
        $bid = Bid::where('supplier_id', $supplier->id)->whereIn('bidding_id', Bidding::where('procurement_project_id', $project->id)->select('id'))->exists();
        $quote = Quotation::where('supplier_id', $supplier->id)->whereIn('rfq_id', Rfq::where('procurement_project_id', $project->id)->select('id'))->exists();
        if (! $bid && ! $quote) {
            throw new WorkflowViolation("{$supplier->name} did not take part in this procurement.");
        }
    }
}
