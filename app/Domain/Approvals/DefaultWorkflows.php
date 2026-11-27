<?php

namespace App\Domain\Approvals;

use App\Models\Agency;
use App\Models\ApprovalWorkflow;
use Illuminate\Support\Facades\DB;

/**
 * Starter approval routes, to be confirmed against the agency's office orders before production.
 * Existing workflows (matched by code) are never changed.
 */
final class DefaultWorkflows
{
    /** @var array<string, array{name: string, subject: string, steps: list<array{0: string, 1: string, 2: bool}>}> */
    public const DEFINITIONS = [
        'MS-DEFAULT' => ['name' => 'Market scoping review', 'subject' => 'market_scoping', 'steps' => [['Department head', 'ppmp.approve', true]]],
        'PPMP-DEFAULT' => ['name' => 'PPMP approval', 'subject' => 'ppmp', 'steps' => [['Department head', 'ppmp.approve', true]]],
        'APP-DEFAULT' => ['name' => 'APP approval by the HoPE', 'subject' => 'app_version', 'steps' => [['Head of Procuring Entity', 'app.approve', false]]],
        'PR-DEFAULT' => ['name' => 'Purchase request approval', 'subject' => 'purchase_request', 'steps' => [
            ['Department head', 'purchase_request.approve', true],
            ['Budget certification', 'budget.certify', false],
        ]],
    ];

    /** @return list<string> Codes created. */
    public static function install(Agency $agency): array
    {
        return DB::transaction(function () use ($agency) {
            $created = [];
            foreach (self::DEFINITIONS as $code => $definition) {
                if (ApprovalWorkflow::where('agency_id', $agency->id)->where('code', $code)->exists()) {
                    continue;
                }
                $workflow = ApprovalWorkflow::create([
                    'agency_id' => $agency->id, 'code' => $code, 'name' => $definition['name'],
                    'subject_type' => $definition['subject'], 'is_active' => true,
                ]);
                foreach ($definition['steps'] as $i => [$name, $permission, $departmentScoped]) {
                    $workflow->steps()->create([
                        'sequence' => $i + 1, 'name' => $name, 'permission_code' => $permission,
                        'department_scoped' => $departmentScoped, 'is_required' => true,
                    ]);
                }
                $created[] = $code;
            }

            return $created;
        });
    }
}
