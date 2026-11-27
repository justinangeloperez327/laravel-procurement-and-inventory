# 17 — Deployment and Bootstrap Guide

**Current repository status:** documentation and initialization only. Laravel application scaffold has not been generated.

## Local prerequisites
Composer, PHP supported by selected Laravel release, PostgreSQL, Node.js/npm and Git. Validate versions before bootstrapping.

## Future Laravel bootstrap
Create a Laravel project in a temporary directory and copy scaffold files into this repository while preserving existing README/docs:

```bash
composer create-project laravel/laravel /tmp/gpims-bootstrap
# Review and copy framework files without overwriting current docs/ or README.md
composer install
cp .env.example .env
php artisan key:generate
# Configure DB_CONNECTION=pgsql and DB_* locally
php artisan migrate
npm install
npm run build
php artisan test
```

Add Livewire, roles, queue worker and storage only after package compatibility checks. Never commit `.env` secrets.

## Release checklist
APP_DEBUG=false, HTTPS, secure sessions, backup/restore drill, migration rollback plan, protected storage, least-privilege DB user, monitoring, authorization tests, regulatory rule validation and government hosting review. Deployment destination and government integration permissions remain undecided.
