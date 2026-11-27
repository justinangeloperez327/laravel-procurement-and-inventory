# 21 — Schema implementation (phases 2–10)

The doc 05 logical schema is now implemented as migrations (`database/migrations/2026_11_28_*`), Eloquent models (`app/Models`) and factories (`database/factories`) for every phase. These are **data structures only**. The workflow actions, state transitions, Filament screens and policies for each phase are still to be built, phase by phase.

## Migrations (in dependency order)
| # | File | Tables |
| --- | --- | --- |
| 1 | `common` | regulatory_sources, regulatory_rule_versions, documents, document_versions, approval_workflows, approval_steps, approval_actions, status_histories, integration_logs |
| 2 | `budget_masters` | fund_sources, account_codes, budget_allocations, budget_adjustments |
| 3 | `catalog` | units_of_measure, item_categories, items, warehouses, warehouse_locations |
| 4 | `suppliers` | suppliers, supplier_contacts, supplier_documents, supplier_beneficial_owners, supplier_sanctions |
| 5 | `planning` | market_scopings, market_scoping_items, ppmps, ppmp_items, apps, app_versions, app_items, app_item_sources |
| 6 | `purchase_requests` | purchase_requests, purchase_request_items |
| 7 | `procurement` | procurement_modes, procurement_mode_rules, procurement_projects, procurement_lots, procurement_project_items, procurement_justifications, procurement_events, procurement_deadlines, procurement_publications |
| 8 | `bidding` | bac_committees, bac_members, twg_members, meetings, meeting_attendees, bac_resolutions, rfqs, quotations, quotation_items, biddings, bids, bid_documents, evaluation_criteria, bid_evaluations, post_qualifications |
| 9 | `contracts` | contract_awards, contracts, contract_items, contract_amendments, contract_securities, framework_agreements, purchase_orders, purchase_order_items, call_off_orders, supplier_performances |
| 10 | `receiving` | deliveries, delivery_items, inspections, inspection_items, delivery_discrepancies, acceptance_reports |
| 11 | `inventory` | inventory_lots, stock_movements, stock_balances, stock_requisitions(+_items), stock_reservations, stock_issuances(+_items), stock_transfers(+_items), physical_counts(+_items) |
| 12 | `property` | assets, asset_assignments, asset_transfers, asset_maintenance, asset_disposals |
| 13 | `finance_compliance` | invoices, invoice_items, payments, payment_allocations, protests, reconsideration_requests, disputes, compliance_flags, conflict_declarations |
| 14 | `budget_ledger` | budget_reservations, budget_obligations, budget_disbursements |

## Conventions
- Money is `decimal(18,2)`; unit costs and quantities are `decimal(18,4)`; rates are `decimal(9,4)`. All timestamps are timezone-aware.
- Foreign keys restrict deletes. Approved records are never cascaded away.
- Top-level records carry `agency_id` and a reference number that is unique per agency.
- Workflow statuses are backed enums in `app/Enums` (for example `PurchaseRequestStatus`, `ProcurementProjectStatus`, `DeliveryStatus`, `StockMovementType`, `ProcurementMode`). Lower-traffic statuses are constrained strings.
- Mutable business records use the `Auditable` trait.

## Integrity enforced by PostgreSQL
- **Append-only** (a `forbid_mutation()` trigger plus the `Immutable` model trait): stock_movements, budget_adjustments, budget_disbursements, document_versions, approval_actions, status_histories, integration_logs.
- **Exactly-once receipt:** `stock_movements.inspection_item_id` is unique, and an `accepted_receipt` movement must reference an inspection line with a positive quantity. A movement can be reversed only once.
- **Idempotency keys** are unique on stock movements, issuances, payments and budget reservations, obligations, disbursements and adjustments.
- **Non-negative stock:** `stock_balances` require on-hand ≥ 0 and reserved ≤ on-hand. There is one row per warehouse, item and lot, with `NULLS NOT DISTINCT` (PostgreSQL 15 or later).
- **Bids:** one bid per supplier per bidding and lot. `bid_amount` stays null until opening. Each evaluation targets exactly one bid or quotation.
- **Other checks:** positive quantities and amounts, date ordering, and no transfer into the same warehouse.

## Deviations from doc 05
- `employees` is not a separate table. Employee number and position live on `users` (phase 1).
- Added line tables the workflows need: `stock_requisition_items`, `stock_issuance_items`, `stock_transfer_items`, `physical_count_items`.
- `procurement_mode_rules` links a mode to `regulatory_rule_versions`, so rule text, effective dates and reviewers live in one place (docs 02 and 08).
- Model names that would clash or confuse: `apps` → `AnnualProcurementPlan`; `procurement_modes` → `ProcurementModeDefinition` (the `ProcurementMode` enum lists the modes); `evaluation_criteria` → `EvaluationCriterion`.
- `procurement_projects.legal_regime` records the applicable law for transitional transactions (L-15).

## Reference data
`php artisan db:seed` syncs roles and permissions and creates one `procurement_modes` row per RA 12009 mode. No monetary thresholds are seeded (docs 08).

## Tests
- `tests/Feature/Schema/FactoryTest.php` builds every model from its factory against PostgreSQL.
- `tests/Feature/Schema/InvariantsTest.php` checks the constraints above.
