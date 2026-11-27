<?php

use App\Domain\Approvals\DefaultWorkflows;
use App\Domain\Identity\GrantRole;
use App\Domain\Identity\SyncRoleCatalog;
use App\Domain\Suppliers\BeneficialOwnership;
use App\Domain\Suppliers\ManageSupplierStatus;
use App\Domain\Suppliers\RegisterSupplier;
use App\Domain\Suppliers\SupplierDocuments;
use App\Domain\Suppliers\SupplierDocumentTypes;
use App\Models\Agency;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Supplier;
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
