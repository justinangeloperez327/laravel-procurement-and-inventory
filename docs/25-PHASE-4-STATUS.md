# Phase 4 — Purchase requests and budget reservation

Status: **done**. Covers L-01 (plan linkage) and the reservation side of docs/09, plus two docs/16 cases: "Unapproved APP expenditure" and "Concurrent budget commitments".

## Lifecycle
draft → submitted → department review → budget review → **approved**, with returned, rejected and cancelled paths (docs/07). Every change goes through `TransitionStatus`, and the history is recorded.

| Step | Who | Rules |
| --- | --- | --- |
| Create | `purchase_request.create` for the department (End User) | Numbered `PR-YYYY-NNNN`. Lines carry quantity × unit cost, computed exactly by PostgreSQL. |
| Submit | `purchase_request.submit` | Needs at least one line and an open fiscal year. **APP coverage:** the PR must link to an item of the *currently approved* APP version for the same fiscal year, and the PR together with other active PRs on that item may not exceed the item's budget (serialized per APP item). Otherwise a documented **plan exception** (legal basis and reason) is required; it raises a *blocking* compliance flag. |
| Department review | `purchase_request.approve` for the department (Department Head) | Through the approval engine. The requester can never approve their own PR. |
| Budget review | `budget.certify` plus `budget.reserve` (Budget Officer) | **Certify funds and reserve**: choose an approved allotment that matches the agency, fiscal year, department, and the fund source and account if set. The PR total is reserved under a row lock and checked against the available balance. Certification is refused while a blocking flag (for example a plan exception) is still open. |
| Return / reject | Current step's approver | Remarks are required. A return restarts the approval round; a rejection is final. Any reservation is released. |
| Cancel | Requester or a department preparer, before procurement starts | A reason is required, and the reservation is released. |

- The agency's `purchase_request` workflow **must include a required `budget.certify` step**; submission is refused otherwise. No PR reaches *approved* without reserved funds.
- Once submitted, the PR and its lines are frozen (`FreezesWhenFinal` and `BelongsToEditableParent`). Corrections happen after a return.

## Budget reservations
`BudgetReservations::reserve()` and `release()`:
- idempotency keys
- at most one active reservation per PR, enforced by a partial unique index
- the amount and allotment are immutable; a reservation can only be released (with a reason) or, in phase 8, converted to an obligation

The allotment view lists its reservations.

## Screens
- **Requests → Purchase Requests:** list scoped by department (budget, procurement, BAC, HoPE and audit roles see all). New request (with an approved-APP item picker), lines, and Submit, Approve, Certify funds and reserve, Return, Reject and Cancel actions, each shown only to the person who can perform it.
- **Budget → Allotments:** a new Reservations tab.

## Schema changes
These were edited in place:
- `purchase_request_items.estimated_total`: now a stored generated column
- `purchase_requests.plan_exception_basis` and `plan_exception_reason`
- `budget_reservations.released_by` and `release_reason`
- a partial unique index allowing one active reservation per PR

## Tests
- `tests/Feature/Requests/PurchaseRequestWorkflowTest.php` (17 tests).
- `tests/Feature/Filament/PurchaseRequestUiTest.php`.
- `tests/Concurrency/ReservationConcurrencyTest.php`: six concurrent certifications of ₱25,000 against ₱60,000 available; exactly two succeed. Without the allotment lock, all six succeed, so the test detects overcommitment.

## Open items
- Moving approved PRs into **procurement** (and to fulfilled) happens in phase 6, when procurement projects consume PR lines.
- Detecting split purchases (L-05) across related sub-threshold PRs is planned for phase 6 mode screening.
- Confirm with the agency whether certification needs an ORS/BURS reference at this stage or only at obligation (phase 8).
