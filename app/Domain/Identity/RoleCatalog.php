<?php

namespace App\Domain\Identity;

/**
 * Default role and permission catalog derived from docs/06-USER-ROLES-PERMISSIONS.md.
 *
 * This is a starting configuration, not an approved agency delegation matrix. Exact positions,
 * quorum and signatories must be approved before production configuration.
 */
final class RoleCatalog
{
    /** @var array<string, string> */
    public const PERMISSIONS = [
        'ppmp.create' => 'Prepare and revise a PPMP',
        'ppmp.approve' => 'Approve a department PPMP',
        'app.consolidate' => 'Consolidate PPMPs into the APP',
        'app.approve' => 'Approve the APP and its revisions',
        'budget.certify' => 'Certify availability of funds',
        'budget.reserve' => 'Reserve and release budget allocations',
        'purchase_request.create' => 'Draft purchase requests',
        'purchase_request.submit' => 'Submit purchase requests for approval',
        'purchase_request.approve' => 'Approve purchase requests',
        'procurement_mode.review' => 'Review and recommend the procurement mode',
        'rfq.manage' => 'Prepare RFQs, postings and bidding documents',
        'bid.open' => 'Open bids at the authorized time',
        'bid.evaluate' => 'Evaluate bids and quotations',
        'bac.recommend' => 'Adopt BAC resolutions and recommendations',
        'award.approve' => 'Approve award (HoPE authority)',
        'contract.manage' => 'Prepare contracts, POs and NTPs',
        'delivery.receive' => 'Receive deliveries',
        'inspection.accept' => 'Inspect and accept deliveries',
        'inventory.issue' => 'Issue stock from warehouses',
        'inventory.adjust' => 'Post approved stock adjustments',
        'asset.assign' => 'Assign property to custodians',
        'invoice.process' => 'Process and match supplier invoices',
        'payment.record' => 'Record payment references',
        'audit.read' => 'Read audit logs and records (read-only)',
    ];

    /** @var array<string, array{name: string, permissions: list<string>, read_only?: bool}> */
    public const ROLES = [
        'system_administrator' => ['name' => 'System Administrator', 'permissions' => []],
        'hope' => ['name' => 'Head of Procuring Entity (HoPE)', 'permissions' => ['app.approve', 'award.approve']],
        'department_head' => ['name' => 'Department Head', 'permissions' => ['ppmp.approve', 'purchase_request.approve']],
        'end_user' => ['name' => 'End User', 'permissions' => ['purchase_request.create', 'purchase_request.submit']],
        'planning_officer' => ['name' => 'Planning Officer', 'permissions' => ['ppmp.create', 'app.consolidate']],
        'budget_officer' => ['name' => 'Budget Officer', 'permissions' => ['budget.certify', 'budget.reserve']],
        'procurement_officer' => ['name' => 'Procurement Officer', 'permissions' => ['rfq.manage', 'contract.manage']],
        'bac_chair' => ['name' => 'BAC Chair', 'permissions' => ['procurement_mode.review', 'bid.open', 'bid.evaluate', 'bac.recommend']],
        'bac_member' => ['name' => 'BAC Member', 'permissions' => ['procurement_mode.review', 'bid.open', 'bid.evaluate', 'bac.recommend']],
        'bac_secretariat' => ['name' => 'BAC Secretariat', 'permissions' => ['rfq.manage']],
        'twg_member' => ['name' => 'TWG Member', 'permissions' => ['bid.evaluate']],
        'observer' => ['name' => 'Observer', 'permissions' => [], 'read_only' => true],
        'receiving_officer' => ['name' => 'Receiving Officer', 'permissions' => ['delivery.receive']],
        'inspection_officer' => ['name' => 'Inspection Officer', 'permissions' => ['inspection.accept']],
        'supply_officer' => ['name' => 'Supply Officer', 'permissions' => ['inventory.issue', 'inventory.adjust']],
        'property_officer' => ['name' => 'Property Officer', 'permissions' => ['asset.assign']],
        'accounting_officer' => ['name' => 'Accounting Officer', 'permissions' => ['invoice.process']],
        'cashier' => ['name' => 'Cashier', 'permissions' => ['payment.record']],
        'auditor' => ['name' => 'Auditor', 'permissions' => ['audit.read'], 'read_only' => true],
    ];

    /**
     * Segregation-of-duties pairs: one user may not hold both roles in the same agency at once.
     *
     * @var list<array{string, string}>
     */
    public const INCOMPATIBLE = [
        // BAC recommendation is segregated from HoPE approval.
        ['hope', 'bac_chair'],
        ['hope', 'bac_member'],
        ['hope', 'bac_secretariat'],
        ['hope', 'twg_member'],
        // Receiving is distinguished from independent inspection.
        ['receiving_officer', 'inspection_officer'],
        // Invoice processing is separated from payment.
        ['accounting_officer', 'cashier'],
    ];

    /** The auditor is read-only and independent of every transactional role. */
    public const INDEPENDENT_ROLES = ['auditor'];

    public static function conflicts(string $a, string $b): bool
    {
        if ($a === $b) {
            return false;
        }

        foreach ([$a, $b] as $code) {
            if (in_array($code, self::INDEPENDENT_ROLES, true)) {
                $other = $code === $a ? $b : $a;

                return $other !== 'observer';
            }
        }

        foreach (self::INCOMPATIBLE as [$x, $y]) {
            if (($x === $a && $y === $b) || ($x === $b && $y === $a)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public static function rolesGranting(string $permission): array
    {
        return array_keys(array_filter(self::ROLES, fn (array $r) => in_array($permission, $r['permissions'], true)));
    }
}
