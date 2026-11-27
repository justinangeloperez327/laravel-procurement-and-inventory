<?php

namespace App\Domain\Suppliers;

use App\Domain\Compliance\ComplianceFlags;
use App\Domain\Shared\WorkflowViolation;
use App\Enums\SanctionType;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierSanction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Suspension and blacklisting orders (L-14). A sanction is never edited or deleted; it is lifted with a reason. */
class SupplierSanctions
{
    public function __construct(private ManageSupplierStatus $status, private ComplianceFlags $flags) {}

    public function record(
        User $actor,
        Supplier $supplier,
        SanctionType $type,
        string $issuingAuthority,
        string $orderReference,
        string $reason,
        string $effectiveFrom,
        ?string $effectiveTo = null,
    ): SupplierSanction {
        SupplierGuard::require($actor, 'supplier.sanction', $supplier);
        if (blank($issuingAuthority) || blank($orderReference) || blank($reason)) {
            throw new WorkflowViolation('The issuing authority, order reference and reason are required.');
        }
        if ($effectiveTo !== null && $effectiveTo <= $effectiveFrom) {
            throw new WorkflowViolation('The sanction must end after it starts.');
        }

        return DB::transaction(function () use ($actor, $supplier, $type, $issuingAuthority, $orderReference, $reason, $effectiveFrom, $effectiveTo) {
            $sanction = SupplierSanction::create([
                'supplier_id' => $supplier->id,
                'sanction_type' => $type,
                'issuing_authority' => $issuingAuthority,
                'order_reference' => $orderReference,
                'reason' => $reason,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'recorded_by' => $actor->id,
            ]);
            AuditLog::record('supplier.sanctioned', $sanction, null, ['type' => $type->value, 'order' => $orderReference]);

            if (Carbon::parse($effectiveFrom)->lte(Carbon::today())) {
                $this->status->syncWithSanctions($supplier->fresh(), $actor);
            }
            $this->flags->raise($supplier->agency_id, $supplier, 'sanctioned_supplier', 'blocking',
                "{$type->label()} under {$orderReference} from {$effectiveFrom}".($effectiveTo ? " to {$effectiveTo}" : '').'.', $actor);

            return $sanction;
        });
    }

    public function lift(User $actor, SupplierSanction $sanction, string $reason): SupplierSanction
    {
        SupplierGuard::require($actor, 'supplier.sanction', $sanction->supplier);
        if (blank($reason)) {
            throw new WorkflowViolation('A reason (e.g. the lifting order) is required.');
        }

        return DB::transaction(function () use ($actor, $sanction, $reason) {
            $locked = SupplierSanction::whereKey($sanction->id)->lockForUpdate()->firstOrFail();
            if ($locked->lifted_at !== null) {
                throw new WorkflowViolation('The sanction is already lifted.');
            }

            $locked->forceFill(['lifted_at' => now(), 'lifted_by' => $actor->id, 'lifted_reason' => $reason])->save();
            AuditLog::record('supplier.sanction_lifted', $locked, null, null, ['reason' => $reason]);
            $this->status->syncWithSanctions($locked->supplier->fresh(), $actor);

            return $locked;
        });
    }
}
