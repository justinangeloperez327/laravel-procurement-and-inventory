 # Phase 0 bootstrap

Laravel 13 and Filament 5 scaffold. PHP 8.4, PostgreSQL. Run composer install, copy .env.example, configure PostgreSQL, php artisan key:generate, php artisan migrate, php artisan filament:assets, php artisan make:filament-user, then explicitly grant is_admin to the authorized account through a trusted database administrator. No public registration. Run php artisan serve and visit /admin. CI and dependency resolution must pass before Phase 0 is accepted. No lockfile yet; generate and commit after successful installation.
