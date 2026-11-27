# 05 — Logical Database Schema

Originally a conceptual design; now implemented. See [21-SCHEMA-IMPLEMENTATION.md](21-SCHEMA-IMPLEMENTATION.md) for the migrations and deviations.

| Domain | Candidate tables |
| --- | --- |
| Organization | agencies, departments, employees, users, roles, permissions, delegations, fiscal_years |
| Planning | market_scopings, market_scoping_items, ppmps, ppmp_items, apps, app_versions, app_items, app_item_sources |
| Budget | fund_sources, account_codes, budget_allocations, budget_reservations, budget_obligations, budget_disbursements, budget_adjustments |
| Requests | purchase_requests, purchase_request_items |
| Procurement | procurement_projects, procurement_project_items, procurement_lots, procurement_modes, procurement_mode_rules, procurement_justifications, procurement_events, procurement_deadlines, procurement_publications |
| Suppliers | suppliers, supplier_contacts, supplier_documents, supplier_beneficial_owners, supplier_sanctions, supplier_performances |
| RFQ/BAC/bidding | rfqs, quotations, quotation_items, bac_committees, bac_members, twg_members, meetings, meeting_attendees, bac_resolutions, biddings, bids, bid_documents, evaluation_criteria, bid_evaluations, post_qualifications |
| Contracts | contract_awards, contracts, contract_items, contract_amendments, contract_securities, purchase_orders, purchase_order_items, framework_agreements, call_off_orders |
| Receiving | deliveries, delivery_items, inspections, inspection_items, delivery_discrepancies, acceptance_reports |
| Inventory | items, item_categories, units_of_measure, warehouses, warehouse_locations, inventory_lots, stock_movements, stock_balances, stock_reservations, stock_requisitions, stock_issuances, stock_transfers, physical_counts |
| Property | assets, asset_assignments, asset_transfers, asset_maintenance, asset_disposals |
| Finance/compliance | invoices, invoice_items, payments, payment_allocations, protests, reconsideration_requests, disputes, compliance_flags, conflict_declarations |
| Common | approval_workflows, approval_steps, approval_actions, documents, document_versions, regulatory_sources, regulatory_rule_versions, audit_logs, status_histories, integration_logs |

## Cardinality highlights
One department has multiple PPMPs. An APP item may consolidate many PPMP items, preserving source mapping. One procurement project can cover multiple PR items and lots. A lot may be awarded to a different supplier. Contracts link to many delivery lines; inspection can accept only some lines. Stock postings refer uniquely to accepted inspection lines.

## Constraints
Unique per-agency/year references, strict foreign keys, idempotent posting, no direct approved-record deletion, effective-dated legal rules, concurrency protection and structured document lineage.
