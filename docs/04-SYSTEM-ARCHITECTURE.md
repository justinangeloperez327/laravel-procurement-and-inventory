# 04 — System Architecture

## Decision
Use a Laravel modular monolith with Blade, Livewire, Tailwind and PostgreSQL. Redis and object storage may be introduced as needs arise. Separate application domains rather than premature microservices.

## Modules
Organization, Planning, Budget, Procurement, Suppliers, Bidding/BAC, Contracts, Receiving, Inventory, Property, Finance, Compliance and Reporting.

## Layers
HTTP/Livewire presentation → request validation → policies → domain actions and state machines → PostgreSQL transactions. Queue notification/report generation only after durable commits.

## Shared infrastructure
Effective-dated rules, approvals, audit events, notifications, document versioning, private storage, numbers/sequences, API integrations and exports.

## Integrity
Use unique idempotency keys for stock/finance posting; decimal quantities/amounts; locking on competing budget/stock changes; immutable ledger transactions with compensating reversals.

## Deployment
Docker-compatible app, database, queue worker and scheduler, HTTPS, separate environments, secrets management, encrypted private storage and backup/restore verification.
