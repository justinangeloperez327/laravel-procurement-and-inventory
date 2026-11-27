<?php

namespace App\Providers;

use App\Domain\Identity\Authorizer;
use App\Domain\Identity\RoleCatalog;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Authorizer::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Scoped permission strings (e.g. Gate::allows('award.approve', $department)) resolve through
        // role assignments and delegations. Other abilities fall through to policies.
        Gate::before(function (User $user, string $ability, array $arguments) {
            if (! array_key_exists($ability, RoleCatalog::PERMISSIONS)) {
                return null;
            }
            $department = collect($arguments)->first(fn ($a) => $a instanceof Department);

            return app(Authorizer::class)->allows($user, $ability, $department);
        });

        Event::listen(Login::class, fn (Login $e) => AuditLog::record('auth.login', $e->user instanceof Model ? $e->user : null));
        Event::listen(Logout::class, fn (Logout $e) => AuditLog::record('auth.logout', $e->user instanceof Model ? $e->user : null));
        Event::listen(Failed::class, fn (Failed $e) => AuditLog::record('auth.failed', null, null, null, [
            'email' => is_string($e->credentials['email'] ?? null) ? mb_substr($e->credentials['email'], 0, 255) : null,
        ]));
    }
}
