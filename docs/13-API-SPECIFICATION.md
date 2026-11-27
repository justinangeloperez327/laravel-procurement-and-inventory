# 13 — API Specification (Draft)

Use `/api/v1`, Sanctum or approved auth, policy authorization, request validation, rate limiting, pagination and auditable mutations. Candidate resources: departments, items, suppliers, ppmps, apps, budgets, purchase-requests, procurement-projects, rfqs, bids, awards, contracts, purchase-orders, deliveries, inspections, warehouses, stock-movements, assets, invoices, reports.

Example commands:
- `POST /purchase-requests/{id}/submit`
- `POST /purchase-requests/{id}/approve`
- `POST /procurement-projects/{id}/screen-mode`
- `POST /inspections/{id}/accept`
- `POST /stock-issuances/{id}/post`

Use `Idempotency-Key` for posting operations, stable resource IDs, version conflict handling, clear errors and trace IDs. External government adapters should only use documented approved APIs. Generate OpenAPI after domain schema acceptance.
