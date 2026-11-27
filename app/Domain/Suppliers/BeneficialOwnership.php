<?php

namespace App\Domain\Suppliers;

use App\Domain\Shared\WorkflowViolation;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierBeneficialOwner;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Beneficial ownership declarations (RA 12009 Art. XV; docs/02 L-11). */
class BeneficialOwnership
{
    public function declare(User $actor, Supplier $supplier, string $name, ?string $percentage, ?string $nationality = null, ?string $controlBasis = null): SupplierBeneficialOwner
    {
        SupplierGuard::require($actor, 'supplier.manage', $supplier);
        if (blank($name)) {
            throw new WorkflowViolation('The beneficial owner name is required.');
        }
        if ($percentage === null && blank($controlBasis)) {
            throw new WorkflowViolation('Give an ownership percentage or the basis of control.');
        }

        return DB::transaction(function () use ($supplier, $name, $percentage, $nationality, $controlBasis) {
            Supplier::whereKey($supplier->id)->lockForUpdate()->first();

            if ($percentage !== null) {
                if (! is_numeric($percentage)) {
                    throw new WorkflowViolation('The ownership percentage must be a number.');
                }
                // Exact decimal comparison in PostgreSQL.
                $invalid = DB::selectOne(
                    'SELECT (?::numeric <= 0 OR ?::numeric + COALESCE(SUM(ownership_percentage), 0) > 100) AS invalid FROM supplier_beneficial_owners WHERE supplier_id = ?',
                    [$percentage, $percentage, $supplier->id],
                )->invalid;
                if ($invalid) {
                    throw new WorkflowViolation('Declared ownership must be above 0% and cannot exceed 100% in total.');
                }
            }

            return SupplierBeneficialOwner::create([
                'supplier_id' => $supplier->id,
                'name' => $name,
                'nationality' => $nationality,
                'ownership_percentage' => $percentage,
                'control_basis' => $controlBasis,
                'declared_on' => now()->toDateString(),
            ]);
        });
    }

    public function verify(User $verifier, SupplierBeneficialOwner $owner): SupplierBeneficialOwner
    {
        SupplierGuard::require($verifier, 'supplier.verify', $owner->supplier);
        if ($owner->verified_at !== null) {
            throw new WorkflowViolation('The declaration is already verified.');
        }

        $owner->forceFill(['verified_by' => $verifier->id, 'verified_at' => now()])->save();
        AuditLog::record('beneficial_owner.verified', $owner, null, ['name' => $owner->name]);

        return $owner;
    }
}
