# Phase 0 bootstrap

Laravel 13 and Filament 5 scaffold. PHP 8.5, PostgreSQL. Run composer install, copy .env.example, configure PostgreSQL, php artisan key:generate, php artisan migrate --seed, php artisan filament:assets, php artisan make:filament-user, then explicitly grant is_admin to the authorized account through a trusted database administrator. No public registration. Run php artisan serve and visit /admin.

Testing standard: Pest 4 with Laravel plugin on PHP 8.5. Run `composer test` or `vendor/bin/pest`. PHPUnit XML remains the underlying Pest configuration.

**Status:** `composer.lock` is committed (resolved for PHP 8.5: Laravel 13.44, Filament 5.12, Livewire 4.4, Pest 4.7). CI installs from the lockfile. Phase 0 is accepted once the CI run on `main` passes on PHP 8.5.
