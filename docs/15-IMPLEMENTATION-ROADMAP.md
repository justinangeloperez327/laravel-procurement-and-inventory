# 15 — Implementation Roadmap

Revised after phases 0–1 and the schema build. The tables, models and factories for every phase already exist ([21-SCHEMA-IMPLEMENTATION.md](21-SCHEMA-IMPLEMENTATION.md)). Each remaining phase delivers the **behaviour** on top of them: domain actions and state machines, policies wired to permission strings, Filament screens, and tests traced to legal requirement IDs.

## Overview

| Phase | Name | Status | Depends on | Size |
| --- | --- | --- | --- | --- |
| 0 | Foundation | Done; CI on PHP 8.5 to confirm | — | — |
| 1 | Identity and organization | Done; MFA and audit export moved to phase 14 | 0 | — |
| 2 | Shared services and master data | Done — see [22-PHASE-2-STATUS.md](22-PHASE-2-STATUS.md) | 1 | M |
| 3 | Planning and budget allocations | Done — see [23-PHASE-3-STATUS.md](23-PHASE-3-STATUS.md) | 2 | L |
| 4 | Purchase requests and budget reservation | Done — see [25-PHASE-4-STATUS.md](25-PHASE-4-STATUS.md) | 3 | M |
| 5 | Supplier registry and compliance | Done — see [24-PHASE-5-STATUS.md](24-PHASE-5-STATUS.md) | 2 | M |
| 6 | Procurement projects and mode screening | Done — see [26-PHASE-6-STATUS.md](26-PHASE-6-STATUS.md) | 4, 5 | L |
| 7 | Solicitation, bidding and evaluation | Done — see [27-PHASE-7-STATUS.md](27-PHASE-7-STATUS.md) | 6 | L |
| 8 | Award, contracts and purchase orders | Next | 7 | L |
| 9 | Delivery, receiving and inspection | Schema ready | 8 | M |
| 10 | Inventory | Schema ready | 9 | L |
| 11 | Property | Schema ready | 9, 10 | M |
| 12 | Finance | Schema ready | 8, 9 | M |
| 13 | Reports and integrations | Schema ready | 3–12 | L |
| 14 | Hardening and deployment | Not started | all | M |

Sizes are relative (S, M, L) and are not commitments. Re-estimate in weeks once phase 2 establishes the delivery pace. The earlier 32-week figure assumed the schema would be built inside each phase, so it no longer applies.

Phases 5 and 3–4 can run in parallel. Phases 11 and 12 can run in parallel after phase 9.

## Every phase includes
1. Domain actions in `app/Domain/<Module>` that run inside DB transactions, lock competing rows, and use idempotency keys for postings.
2. State transitions through the phase 2 transition service, which writes `status_histories`.
3. Policies and `Gate` checks using the scoped permission strings in `RoleCatalog`. Add new permissions there, never as `is_admin` checks.
4. Filament resources, relation managers and workflow actions. Finalized records must not be editable through generic CRUD.
5. Pest tests for business rules, authorization and PostgreSQL concurrency, tagged with doc 02 legal IDs and doc 16 test cases.
6. Documentation updates and a phase status note.

## Phases

### 0 — Foundation ✅
Laravel 13, Filament 5, PHP 8.5, PostgreSQL, Pest 4, CI, locked dependencies.
**To close:** a green CI run on `main` using PHP 8.5.

### 1 — Identity and organization ✅
Agencies, departments and fiscal years; role catalog; scoped authorizer; segregation of duties; time-boxed delegation; append-only audit log; access-control screens. See [20-PHASE-1-STATUS.md](20-PHASE-1-STATUS.md).
**Agency input:** approved role-to-permission matrix, incompatible role pairs and maximum delegation length.

### 2 — Shared services and master data ✅
Builds the services every later phase uses.
- **Reference numbering:** a locked per-agency, per-year, per-type sequence (for example `PR-2026-0001`). No gaps from rolled-back transactions unless the agency accepts gaps.
- **State machine service:** allowed transitions per model, a required reason on return, reject and cancel paths, and a `status_histories` entry for every change.
- **Approval engine:** approval workflows, steps and actions. It resolves the approver through the authorizer, records the delegation used, and prevents the same user approving twice where segregation requires it.
- **Document service:** private disk, SHA-256 hash, immutable versions, classification-based access, time-limited download URLs. Sealed bid documents are never served before opening.
- **Regulatory rule lookup:** returns the approved rule version in effect on a given date; draft and retired versions are never enforced.
- **Master data screens:** regulatory sources and rule versions (with review workflow), fund sources, account codes, units of measure, item categories, items and warehouses.

**Tests:** concurrent reference numbering, rejected transitions, delegated approval, document hash and version immutability, and the legacy-regime rule test (L-15).

### 3 — Planning and budget allocations (L-01, L-02) ✅
- Market scoping, then PPMP preparation, submission, department approval and versioned revision.
- APP consolidation with `app_item_sources` lineage, HoPE approval, and versioned revisions. An approved version is never edited; it is superseded.
- Budget allocations with an authorization reference, adjustments (append-only), and the available-funds calculation: allocation + adjustments − active reservations − obligations.

**Tests:** unapproved APP cannot be used; revision history is preserved; adjustment ledger reconciles.
**Agency input:** account code (UACS) list, fund sources and the PPMP/APP formats.

### 4 — Purchase requests and budget reservation (L-01) ✅
- PR state machine (doc 07) with department and budget review through the approval engine.
- Each PR must link to an approved APP item, or go through a documented lawful exception.
- Budget certification and atomic reservation under row locks. Reservations are released on rejection or cancellation.

**Tests:** concurrent reservations cannot overcommit; PR without APP coverage is blocked; return and resubmit keeps history.

### 5 — Supplier registry and compliance (L-11, L-14) ✅
- Supplier registration, contacts and eligibility documents with expiry tracking.
- Beneficial ownership and sanctions (suspension and blacklisting with effective dates).
- Compliance flags raised automatically for expired documents and sanctioned suppliers.
- Conflict-of-interest declarations.

**Tests:** a sanctioned or ineligible supplier cannot be invited or awarded; expiry raises a flag.

### 6 — Procurement projects and mode screening (L-04, L-05, L-06) ✅
- Procurement projects and lots built from approved PR lines.
- Mode screening against approved rule versions, with justification and reviewer decision.
- Flags for related sub-threshold purchases that suggest splitting.
- BAC committees, TWG, meetings with quorum and attendance, and resolutions.
- Procurement events, deadlines (extended only by new linked rows) and publications.

**Tests:** ineligible mode is rejected; splitting flag is raised; actions require quorum.
**Agency input:** current IRR, GPPB resolutions and thresholds with a named legal reviewer per rule. No hardcoded peso ceilings.

### 7 — Solicitation, bidding and evaluation (L-07, L-08, L-12) ✅
- RFQ and quotations for small value procurement and other alternative modes.
- Bidding with sealed bids, immutable receipt timestamps and late-bid handling.
- Authorized opening: amounts and sealed documents stay unreadable until then.
- Evaluation criteria are published and locked before opening.
- Evaluation per evaluator, with conflict-of-interest recusal; post-qualification.
- Protests and reconsideration requests that block award.
- BAC recommendation by resolution.

**Tests:** late bid, early read of a confidential bid, improper change to criteria, conflicted evaluator.

### 8 — Award, contracts and purchase orders (L-09, L-13)
- HoPE award approval (`award.approve`, never given by `is_admin`), blocked while an award-blocking protest is pending.
- Notice of award, contract signing, required performance and warranty securities, notice to proceed, and amendments with approval.
- Purchase orders; the budget reservation is converted to an obligation.

**Tests:** pending protest stops award; a missing security blocks the contract transition; an unauthorized amendment is denied.

### 9 — Delivery, receiving and inspection (L-16)
- Delivery against the purchase order, then receipt.
- Independent inspection: the inspector must differ from the receiver.
- Line-level partial acceptance or rejection, discrepancy follow-up, and the IAR.

**Tests:** after a partially failed inspection, only the accepted quantity is available for posting; over-delivery is flagged.
**Agency input:** official IAR form.

### 10 — Inventory (L-16)
- Exactly-once posting of accepted inspection lines to the stock ledger, with balances maintained under locks.
- A ledger rebuild and reconciliation command.
- RIS requisition, approval, reservation, issuance and acknowledgement.
- Transfers with in-transit tracking.
- Physical counts, with variance investigation and signed adjustments.
- Lot, expiry and serial-number rules.

**Tests:** duplicate receipt, concurrent issues cannot over-issue, ledger and balance reconciliation.
**Agency input:** RIS, RSMI, stock card and RPCI formats.

### 11 — Property (L-16)
- Classification into consumable, semi-expendable or PPE per COA rules, never from the procurement mode alone.
- Asset registration from accepted lines.
- ICS or PAR assignment with custodian acknowledgement.
- PTR transfers, maintenance records, and IIRUP disposal with approval.
- Physical count reconciliation.

**Tests:** assets cannot be registered without an accepted line; transfers require approval; disposed assets are frozen.
**Agency input:** COA classification thresholds and the ICS, PAR, PTR, RPCPPE and IIRUP forms.

### 12 — Finance (L-16)
- Invoice capture and matching against the accepted quantity and price.
- Disputes.
- Payment references and allocations recorded from the accounting system.
- Disbursements against obligations; closure releases any unused reservation or obligation.
- Supplier performance ratings.

**Tests:** an invoice that exceeds the accepted quantity is flagged; a disbursement never reduces the allocation twice.

### 13 — Reports and integrations (L-03)
- APP and PPMP outputs; Procurement Monitoring Report (PMR).
- Award, budget, stock and property registers.
- COA and GPPB form generation using validated templates.
- `/api/v1` (doc 13) with Sanctum, idempotency keys and an OpenAPI spec.
- PhilGEPS and other adapters, only through documented and authorized APIs, logged in `integration_logs`.

**Agency input:** integration permissions and current templates.

### 14 — Hardening and deployment (L-17)
- MFA for privileged users.
- Tamper-evident audit export (hash chain or external signing).
- Full doc 14 threat-case and authorization suite; privacy and retention review.
- Performance tests on realistic volumes; UAT with synthetic agency scenarios.
- Docker deployment with a queue worker and scheduler, backup and restore drill, and the doc 17 release checklist.

**Agency input:** hosting destination and government hosting review.

## MVP
Phases 0–10, plus basic property registration (phase 11), invoices and payments (phase 12), and the PMR and registers (phase 13).

The MVP procurement modes are competitive bidding, small value procurement, direct contracting, direct acquisition, repeat order and negotiated procurement.

**Deferred until after MVP:**
- framework agreements and call-off orders
- competitive dialogue
- unsolicited offers with bid matching
- direct procurement for science, technology and innovation
- the supplier portal
- the external API, beyond what the integrations need

## Schema change policy
Until the first staging deployment, phase 2–14 work may edit the existing `2026_11_28_*` migrations in place when a workflow exposes a design gap, since no database depends on them yet. After the first staging deployment, every change must be a new migration.

## Definition of done
For every phase:
- Requirements approved, with the agency inputs above received or the dependency explicitly waived.
- Legal traceability recorded where relevant.
- Domain actions and policies implemented, with no CRUD path that bypasses them.
- Passing tests, including authorization and concurrency.
- Migrations documented.
- Acceptance by the agency.
- Operational guides and the phase status note updated.
