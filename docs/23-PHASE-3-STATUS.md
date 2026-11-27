# Phase 3 — Planning and budget allocations

Status: **done**. Covers L-01 (planning: market scoping, PPMP, APP and revisions) and the allocation side of docs/09. Budget reservations move to phase 4, alongside purchase requests.

## Planning workflow
| Record | Flow | Rules |
| --- | --- | --- |
| Market scoping | draft → submitted → approved (or returned) | Prepared by `ppmp.create` holders of the department; reviewed by the department head. Approved scoped prices can be referenced by PPMP lines. |
| PPMP | draft → submitted → approved (or returned) | Needs at least one line, and every line needs a proposed procurement mode. Line budgets are computed by PostgreSQL (`quantity × unit cost`, rounded to centavos). The total is fixed at submission. |
| PPMP revision | new version → approval supersedes the old one | Same reference number with the next version number; lines are copied. Only one revision can be in progress. The old version stays in force until the revision is approved. |
| APP | opened per fiscal year (one per agency) | `app.consolidate` (agency-wide) prepares it; approval needs `app.approve` (HoPE). |
| APP consolidation | re-runnable sync | Adds approved PPMP lines of the fiscal year, grouped by mode, item category and fund source. Drops lines whose PPMP was superseded. Each APP item's budget is recomputed in SQL as the exact sum of its sources (`app_item_sources` lineage). |
| APP revision | new version → approval supersedes the old one | Copies items and lineage. Submission is blocked until stale sources are re-consolidated. |
| APP coverage | `AppCoverage::usableItems()` / `isUsable()` | Only items of the currently approved version may support spending. Draft, submitted, returned and superseded versions never qualify (docs/16 "Unapproved APP expenditure"). Phase 4 purchase requests will use this. |

Submission and decisions go through `PlanApproval`, which uses the phase 2 approval engine and status transitions. Plans are returned with remarks, never rejected. The initiator cannot approve. A closed fiscal year blocks submission.

**Freezing:** `FreezesWhenFinal` (headers) and `BelongsToEditableParent` (lines) refuse any change to a submitted or approved plan except the workflow stamps. Corrections are made by revision.

## Budget allotments
| Action | Rules |
| --- | --- |
| Record allotment | `budget.allocate` (new permission, Budget Officer). Requires a fund source, account code, positive amount and authorization reference (GAA, SARO or sub-allotment). Numbered `ALLOT-YYYY-NNNN`. |
| Approve | `budget.allocate`, by an officer other than the recorder. After approval the amount is frozen. |
| Augment, reduce, realign | Append-only `budget_adjustments` with an authorization and reason. Rows are locked, so a reduction or realignment can never exceed the available balance. Realignment links its `out` and `in` entries and is limited to the same fiscal year. Each request carries an idempotency key: a replay returns the original entry, and reusing a key for a different adjustment is refused. |
| Balance (`BudgetLedger`) | Exact SQL sums converted to integer centavos (no floats; `Money` helper). Available = adjusted allotment − active reservations − active obligations. Disbursements reduce unpaid obligations only and are never subtracted from available again. |

## Screens
- **Planning:** Market Scopings, PPMPs, Annual Procurement Plan. Each has Submit, Approve, Return, Revise and Consolidate actions, shown only to users who can perform them.
- **Budget:** Allotments, showing the live balance and the adjustment ledger.
- Department planners and heads see only their departments' plans. Agency-wide planning, budget and audit roles see all of them.

**Setup:** `php artisan gpims:install-default-workflows {agency-code}` creates starter approval routes for market scoping, PPMP, APP and purchase requests. Confirm them against the office orders before use.

## Schema changes
These were edited in place:
- `market_scopings`: `submitted_at`, `approved_by`, `approved_at`
- `ppmp_items.estimated_budget`: now a stored generated column
- `app_versions.submitted_at`
- `budget_allocations.recorded_by`

## Tests
- `tests/Feature/Planning/*`: PPMP workflow, revisions, APP consolidation, coverage and revisions.
- `tests/Feature/Budget/BudgetAllocationTest.php`: allotments, adjustments and reconciliation.
- `tests/Feature/Filament/PlanningUiTest.php`: the screens and department scoping.
- `tests/Concurrency/BudgetConcurrencyTest.php`: 6 concurrent reductions of ₱300 against ₱1,000; exactly 3 succeed. Without the row lock, 4 succeed, so the test detects the race.

## Open items
- Official PPMP and APP output formats (GPPB templates) are deferred to phase 13.
- Indicative versus final APP rules, and the timing of APP updates (for example semi-annual), need confirmation against the current IRR.
- Closing allotments at fiscal-year end and continuing appropriations are not built yet; they are needed before year-end.
- Agency inputs: the account code list, fund sources, allotment documents, and confirmed approval routes.
