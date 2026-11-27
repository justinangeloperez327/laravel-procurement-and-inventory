<?php

use App\Domain\Approvals\DefaultWorkflows;
use App\Domain\Bac\BacComposition;
use App\Domain\Bac\BacMeetings;
use App\Domain\Budget\ApproveBudgetAllocation;
use App\Domain\Budget\CreateBudgetAllocation;
use App\Domain\Compliance\ConflictDeclarations;
use App\Domain\Identity\GrantRole;
use App\Domain\Identity\SyncRoleCatalog;
use App\Domain\Planning\ConsolidatePpmps;
use App\Domain\Planning\CreateApp;
use App\Domain\Planning\CreatePpmp;
use App\Domain\Planning\PlanApproval;
use App\Domain\Regulatory\ApproveRuleVersion;
use App\Domain\Requests\CreatePurchaseRequest;
use App\Domain\Requests\PurchaseRequestWorkflow;
use App\Domain\Suppliers\BeneficialOwnership;
use App\Domain\Suppliers\ManageSupplierStatus;
use App\Domain\Suppliers\RegisterSupplier;
use App\Domain\Suppliers\SupplierDocuments;
use App\Domain\Suppliers\SupplierDocumentTypes;
use App\Enums\ApprovalDecision;
use App\Enums\ProcurementMode;
use App\Models\AccountCode;
use App\Models\Agency;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Meeting;
use App\Models\ProcurementModeDefinition;
use App\Models\ProcurementModeRule;
use App\Models\ProcurementProject;
use App\Models\PurchaseRequest;
use App\Models\RegulatoryRuleVersion;
use App\Models\RegulatorySource;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit', 'Concurrency');
pest()->use(RefreshDatabase::class)->in('Feature');
// Concurrency tests need committed data visible to other connections, so they migrate instead of wrapping in a transaction.
pest()->use(DatabaseMigrations::class)->in('Concurrency');

function seedRoles(): void
{
    app(SyncRoleCatalog::class)();
}

function role(string $code): Role
{
    return Role::where('code', $code)->firstOrFail();
}

function adminFor(Agency $agency): User
{
    return User::factory()->forAgency($agency)->admin()->create();
}

/** Grants a role through the real domain action, using a separate administrator as grantor. */
function grant(User $user, string $roleCode, ?Department $department = null, ?DateTimeInterface $expiresAt = null): RoleAssignment
{
    $admin = User::factory()->forAgency($user->agency)->admin()->create();

    return app(GrantRole::class)($admin, $user, role($roleCode), $department, $expiresAt ? CarbonImmutable::instance($expiresAt) : null, 'Test designation');
}

/**
 * Runs $task in $workers forked processes at the same time, each with its own database connection,
 * and returns their results (or thrown messages as ['error' => ...]) in worker order.
 *
 * @return list<mixed>
 */
function concurrently(int $workers, Closure $task): array
{
    $files = [];
    $pids = [];
    $start = microtime(true) + 0.3; // common start line so workers actually overlap

    for ($i = 0; $i < $workers; $i++) {
        $files[$i] = tempnam(sys_get_temp_dir(), 'gpims-worker-');
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Could not fork a worker.');
        }
        if ($pid === 0) {
            // Child: never touch the parent's socket. Open a fresh connection under another name.
            config(['database.connections.worker' => config('database.connections.'.config('database.default'))]);
            DB::setDefaultConnection('worker');
            time_sleep_until($start);
            try {
                $result = ['ok' => $task($i)];
            } catch (Throwable $e) {
                $result = ['error' => $e->getMessage()];
            }
            file_put_contents($files[$i], serialize($result));
            // SIGKILL skips PHPUnit shutdown handlers and PDO destructors that would disturb the parent.
            posix_kill(posix_getpid(), SIGKILL);
        }
        $pids[$i] = $pid;
    }

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    return array_map(function (string $file) {
        $result = unserialize((string) file_get_contents($file));
        unlink($file);

        return $result === false ? ['error' => 'worker produced no result'] : ($result['ok'] ?? $result);
    }, $files);
}

/**
 * A ready agency for planning and budget tests: an open fiscal year, a department, default approval
 * workflows, and users holding the planning and budget roles.
 *
 * @return object{agency: Agency, fy: FiscalYear, dept: Department, planner: User, head: User, appPlanner: User, hope: User, budget: User, budget2: User}
 */
function planningContext(): object
{
    seedRoles();
    $agency = Agency::factory()->create();
    DefaultWorkflows::install($agency);
    $dept = Department::factory()->for($agency)->create();
    $user = fn (string $role, ?Department $scope = null) => tap(User::factory()->forAgency($agency)->create(), fn (User $u) => grant($u, $role, $scope));

    return (object) [
        'agency' => $agency,
        'fy' => FiscalYear::factory()->for($agency)->forYear(2027)->create(),
        'dept' => $dept,
        'planner' => $user('planning_officer', $dept),
        'head' => $user('department_head', $dept),
        'appPlanner' => $user('planning_officer'),
        'hope' => $user('hope'),
        'budget' => $user('budget_officer'),
        'budget2' => $user('budget_officer'),
    ];
}

/**
 * Agency with supplier staff: a registrar (supplier.manage), a verifier (supplier.verify), a sanctioning
 * officer and a compliance reviewer (HoPE).
 *
 * @return object{agency: Agency, registrar: User, verifier: User, hope: User, officer: User}
 */
function supplierContext(): object
{
    seedRoles();
    Storage::fake('local');
    $agency = Agency::factory()->create();
    $user = fn (string $role) => tap(User::factory()->forAgency($agency)->create(), fn (User $u) => grant($u, $role));

    return (object) [
        'agency' => $agency,
        'registrar' => $user('bac_secretariat'),
        'verifier' => $user('bac_secretariat'),
        'officer' => $user('procurement_officer'),
        'hope' => $user('hope'),
    ];
}

/** A fully verified, active supplier, built through the real domain actions. */
function eligibleSupplier(object $c, array $attributes = []): Supplier
{
    $supplier = app(RegisterSupplier::class)($c->registrar, array_merge([
        'name' => fake()->company(), 'tin' => fake()->unique()->numerify('#########000'), 'business_type' => 'corporation',
    ], $attributes));
    $docs = app(SupplierDocuments::class);
    foreach (SupplierDocumentTypes::REQUIRED as $type) {
        $doc = $docs->add($c->registrar, $supplier, $type, UploadedFile::fake()->createWithContent("{$type}.pdf", "%PDF-1.4 {$type}"),
            'REF-'.$type, now()->subMonth()->toDateString(), now()->addYear()->toDateString());
        $docs->verify($c->verifier, $doc);
    }
    $owners = app(BeneficialOwnership::class);
    $owners->verify($c->verifier, $owners->declare($c->registrar, $supplier, 'Juan dela Cruz', '100', 'Filipino'));

    return app(ManageSupplierStatus::class)->activate($c->verifier, $supplier->fresh());
}

/**
 * planningContext() plus a department requester, an approved APP with one item (₱100,000 of SVP
 * supplies), and an approved ₱1,000,000 allotment for the department.
 */
function requestContext(): object
{
    $c = planningContext();
    $c->requester = tap(User::factory()->forAgency($c->agency)->create(), fn (User $u) => grant($u, 'end_user', $c->dept));
    $c->uom = UnitOfMeasure::factory()->create();

    $plans = app(PlanApproval::class);
    $ppmp = app(CreatePpmp::class)($c->planner, $c->dept, $c->fy);
    $ppmp->items()->create(['description' => 'Office supplies', 'unit_of_measure_id' => $c->uom->id, 'quantity' => 400,
        'estimated_unit_cost' => '250.00', 'proposed_mode' => ProcurementMode::SmallValueProcurement]);
    $plans->submit($c->planner, $ppmp);
    $plans->decide($c->head, $ppmp, ApprovalDecision::Approved);
    $version = app(CreateApp::class)($c->appPlanner, $c->fy);
    app(ConsolidatePpmps::class)($c->appPlanner, $version);
    $plans->submit($c->appPlanner, $version);
    $plans->decide($c->hope, $version, ApprovalDecision::Approved);
    $c->appItem = $version->items()->sole();

    $c->allocation = app(ApproveBudgetAllocation::class)($c->budget2, app(CreateBudgetAllocation::class)(
        $c->budget, $c->fy, FundSource::factory()->create(['agency_id' => $c->agency->id]),
        AccountCode::factory()->create(), '1000000.00', 'SARO-2027-0001', $c->dept,
    ));

    return $c;
}

/** A draft PR for the context's department with one line of $qty × $cost. */
function draftRequest(object $c, string $qty = '100', string $cost = '250.00', bool $linked = true): PurchaseRequest
{
    $pr = app(CreatePurchaseRequest::class)($c->requester, $c->dept, $c->fy, 'Office supplies for Q1', $linked ? $c->appItem : null);
    $pr->items()->create(['description' => 'Bond paper, A4', 'unit_of_measure_id' => $c->uom->id, 'quantity' => $qty, 'estimated_unit_cost' => $cost]);

    return $pr;
}

/**
 * requestContext() plus procurement: procurement mode rows, a procurement officer, a constituted BAC
 * (chair, two members, secretariat), a legal reviewer, and an approved Small Value Procurement rule
 * (goods, ABC at most ₱50,000, canvass evidence required).
 */
function procurementContext(): object
{
    $c = requestContext();
    foreach (ProcurementMode::cases() as $mode) {
        ProcurementModeDefinition::updateOrCreate(['code' => $mode], ['name' => $mode->label()]);
    }
    $user = fn (string $role) => tap(User::factory()->forAgency($c->agency)->create(), fn (User $u) => grant($u, $role));
    $c->officer = $user('procurement_officer');
    $c->secretariat = $user('bac_secretariat');
    $c->chair = $user('bac_chair');
    $c->member1 = $user('bac_member');
    $c->member2 = $user('bac_member');
    $c->lawyer = $user('legal_reviewer');

    $bac = app(BacComposition::class);
    $c->committee = $bac->constitute($c->hope, 'BAC for Goods', 'goods', 'OO-2027-001', now()->subMonth()->toDateString());
    $bac->addMember($c->hope, $c->committee, $c->chair, 'chair', 'OO-2027-001', now()->subMonth()->toDateString());
    $bac->addMember($c->hope, $c->committee, $c->member1, 'member', 'OO-2027-001', now()->subMonth()->toDateString());
    $bac->addMember($c->hope, $c->committee, $c->member2, 'member', 'OO-2027-001', now()->subMonth()->toDateString());
    $bac->addMember($c->hope, $c->committee, $c->secretariat, 'secretariat', 'OO-2027-001', now()->subMonth()->toDateString());

    $source = RegulatorySource::factory()->create(['reference' => 'RA 12009 IRR']);
    $c->svpRule = RegulatoryRuleVersion::factory()->create([
        'regulatory_source_id' => $source->id, 'rule_code' => 'SVP-GOODS', 'version' => 1, 'provision' => 'Small value procurement',
        'legal_reference' => 'IRR (test fixture)', 'approved_interpretation' => 'Test fixture threshold, not an authoritative amount.',
        'test_reference' => 'tests/Feature/Procurement', 'effective_from' => '2020-01-01', 'effective_to' => null, 'status' => 'draft',
        'conditions' => ['abc_max' => '50000.00', 'categories' => 'goods', 'requires_evidence' => 'canvass'],
        'prepared_by' => $c->officer->id,
    ]);
    app(ApproveRuleVersion::class)($c->lawyer, $c->svpRule);
    ProcurementModeRule::create([
        'procurement_mode_id' => ProcurementModeDefinition::where('code', 'small_value_procurement')->value('id'),
        'regulatory_rule_version_id' => $c->svpRule->id, 'rule_type' => 'eligibility', 'is_blocking' => true,
    ]);
    $c->category = ItemCategory::factory()->create(['name' => 'Office supplies']);
    $c->paper = Item::factory()->create(['agency_id' => $c->agency->id, 'item_category_id' => $c->category->id, 'name' => 'Bond paper']);

    return $c;
}

/** An approved, funded purchase request with one catalog line of $qty × $cost. */
function approvedRequest(object $c, string $qty = '100', string $cost = '250.00'): PurchaseRequest
{
    $pr = app(CreatePurchaseRequest::class)($c->requester, $c->dept, $c->fy, 'Supplies', $c->appItem);
    $pr->items()->create(['item_id' => $c->paper->id, 'description' => 'Bond paper', 'unit_of_measure_id' => $c->uom->id, 'quantity' => $qty, 'estimated_unit_cost' => $cost]);
    $flow = app(PurchaseRequestWorkflow::class);
    $flow->submit($c->requester, $pr);
    $flow->approve($c->head, $pr);
    $flow->certifyFunds($c->budget, $pr, $c->allocation);

    return $pr->fresh();
}

/** A held BAC meeting with the chair and both members present. */
function quorateMeeting(object $c, ?ProcurementProject $project = null): Meeting
{
    $meetings = app(BacMeetings::class);
    $meeting = $meetings->schedule($c->secretariat, $c->committee, 'regular', now()->toDateTimeString(), $project);

    return $meetings->recordHeld($c->secretariat, $meeting, [$c->chair->id, $c->member1->id, $c->member2->id]);
}

/** Declares "no conflict" for each user on the project. */
function declareNoConflict(ProcurementProject $project, User ...$users): void
{
    foreach ($users as $u) {
        app(ConflictDeclarations::class)->declare($u, $project, null, false);
    }
}
