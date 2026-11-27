# 03 — Software Requirements

## Functional requirements
| ID | Module | Capabilities |
| --- | --- | --- |
| FR-01 | Identity | Users, roles, permissions, delegated authority, access history |
| FR-02 | Master data | Agency, departments, staff, catalogs and fiscal years |
| FR-03 | Planning | Market scoping, PPMP, APP, revisions and lineage |
| FR-04 | Budget | Allocations, reservations, obligations and available-funds checks |
| FR-05 | Purchase requests | Items, rationale, document attachments and approval lifecycle |
| FR-06 | Suppliers | Registrations, eligibility documents, expiries, ownership and sanctions |
| FR-07 | Sourcing | Procurement mode screening, RFQ, tender/bids and BAC procedures |
| FR-08 | Evaluation | Published criteria, technical/financial evaluation and post-qualification |
| FR-09 | Contract | Award, contracts, PO, NTP, securities, milestones and amendments |
| FR-10 | Receiving | Receipt, independent inspection, partial acceptance and discrepancy |
| FR-11 | Stock | Warehouses, movement ledger, transfer, reservation, issue and counting |
| FR-12 | Property | Asset classification, custodian assignment, return and disposal |
| FR-13 | Finance | Invoices, accepted-quantity matching and payment references |
| FR-14 | Compliance | COI, protests, sanctions, exceptions, documents and audit |
| FR-15 | Reports | APP/PPMP, PMR, awards, budget, stock and property registers |

## Business invariants
- Finalized plan, award, contract and stock postings may not be silently overwritten.
- No financial commitment without applicable funding and approval.
- Inventory increases only through accepted and unique postings.
- Procurement evaluation criteria cannot be retroactively altered without legally permitted process.
- No cross-agency/department access without delegated permission.

## Nonfunctional requirements
Responsive UI, accessibility, PostgreSQL transactions, concurrency control, secure file storage, auditability, backups, decimal precision, integration reliability and automated tests.
