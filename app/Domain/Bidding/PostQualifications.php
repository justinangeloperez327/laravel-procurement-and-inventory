<?php

namespace App\Domain\Bidding;

use App\Domain\Shared\WorkflowViolation;
use App\Domain\Suppliers\SupplierEligibility;
use App\Enums\ProcurementProjectStatus;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\PostQualification;
use App\Models\ProcurementProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Post-qualification of the best-ranked offer (L-08). If it fails, the next-ranked passing offer is
 * post-qualified. A supplier that is no longer eligible (e.g. sanctioned since bidding) fails
 * automatically with the reasons recorded.
 */
class PostQualifications
{
    public function __construct(private Evaluation $evaluation, private SupplierEligibility $eligibility) {}

    public function startNext(User $actor, ProcurementProject $project): PostQualification
    {
        BiddingGuard::require($actor, 'bid.evaluate', $project);
        BiddingGuard::requireStatus($project, [ProcurementProjectStatus::PostQualification]);

        return DB::transaction(function () use ($project) {
            $project->newQuery()->whereKey($project->id)->lockForUpdate()->first();
            if ($project->postQualifications()->whereIn('result', ['pending', 'passed'])->exists()) {
                throw new WorkflowViolation('A post-qualification is already pending or passed.');
            }
            $tried = $project->postQualifications()->pluck('supplier_id')->all();
            $next = $this->evaluation->rank($project)->first(fn (Offer $o) => $o->passed && ! in_array($o->supplier()->id, $tried, true));
            if ($next === null) {
                throw new WorkflowViolation('No remaining offer can be post-qualified. Declare a failure of procurement.');
            }

            $pq = PostQualification::create([
                'procurement_project_id' => $project->id,
                'procurement_lot_id' => $next->lotId(),
                'supplier_id' => $next->supplier()->id,
                'bid_id' => $next->model instanceof Bid ? $next->model->id : null,
                'quotation_id' => $next->model instanceof Bid ? null : $next->model->id,
                'started_at' => now(),
                'result' => 'pending',
            ]);

            $check = $this->eligibility->check($next->supplier());
            if (! $check->eligible()) {
                $pq->forceFill(['result' => 'failed', 'completed_at' => now(), 'findings' => 'Not eligible: '.implode('; ', $check->reasons)])->save();
            }
            AuditLog::record('post_qualification.started', $pq, null, ['supplier' => $next->supplier()->name, 'result' => $pq->result]);

            return $pq;
        });
    }

    public function record(User $actor, PostQualification $pq, bool $passed, string $findings): PostQualification
    {
        $project = $pq->procurementProject;
        BiddingGuard::require($actor, 'bid.evaluate', $project);
        BiddingGuard::requireIndependent($actor, $project, $pq->supplier);
        if ($pq->result !== 'pending') {
            throw new WorkflowViolation("This post-qualification is already {$pq->result}.");
        }
        if (blank($findings)) {
            throw new WorkflowViolation('Record the findings of the post-qualification.');
        }
        if ($passed) {
            $this->eligibility->assert($pq->supplier);
        }

        $pq->forceFill(['result' => $passed ? 'passed' : 'failed', 'findings' => $findings, 'completed_at' => now(), 'performed_by' => $actor->id])->save();
        AuditLog::record('post_qualification.'.($passed ? 'passed' : 'failed'), $pq, null, ['supplier' => $pq->supplier->name]);

        return $pq;
    }
}
