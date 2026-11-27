# Filament application architecture

Decision: Filament is the primary authenticated internal UI, replacing hand-built administrative Blade/Livewire screens. Target Laravel 13, PHP 8.4, PostgreSQL and a compatible stable Filament release (verify Composer resolution before locking versions).

## Panel
One internal panel at /admin, with login and no public registration. Enforce panel access and resource policies; no default production credentials. Separate supplier portal only when authorized.

## Structure
- app/Filament/Resources: master data and transaction resources
- app/Filament/Pages: workflow queues, APP consolidation, procurement evaluation, inspection and reconciliation
- app/Filament/Widgets: role-scoped KPIs
- app/Providers/Filament: panel configuration
- app/Domain: transactional business actions, rules and state transitions

Use relation managers for line items, actions for approvals, and policies for every record operation. Never allow generic CRUD to bypass finalized-record controls. Do not expose confidential bid documents in global search.

## Phase 0 acceptance
Install compatible Filament panel builder, register panel provider, configure PostgreSQL, create an explicitly authorized local test user, verify login and protected routes, run migrations and CI. No deployment or business modules in this phase.

## Bootstrap status
Framework and dependency installation remain pending until a runnable Composer environment or verified scaffold is available. This decision supersedes the frontend choice in earlier planning documents.
