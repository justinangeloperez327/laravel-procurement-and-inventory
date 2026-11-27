# Phase 2 — Shared services and master data

Status: **done**. Builds the shared services that every later phase uses (docs 04 and 15).

## Services (`app/Domain`)
| Service | What it guarantees |
| --- | --- |
| `Shared\ReferenceNumbers` | Issues `PR-2026-0001`-style numbers per agency, type and year. The counter row is locked, so concurrent requests never get duplicates. Called inside the record's own transaction, a rollback also rolls back the counter, so there are no gaps. |
| `Shared\TransitionStatus` + `HasTransitions` | The only way to change a workflow status. It locks the row, checks the transition against the enum's state machine (doc 07, implemented for PR, procurement project, plan and delivery statuses), requires a reason for return/reject/cancel/fail, and appends to `status_histories`. |
| `Approvals\ApprovalEngine` | Routes `Approvable` records through the agency's active approval workflow, picking the version by effective date. Each step needs a scoped permission resolved by the Authorizer. Delegated authority is accepted and recorded with the principal. The initiator can never approve, and one person (or their principal) decides at most one step per round. A return restarts the round; a rejection is final. The engine records decisions only; callers move the status. |
| `Documents\DocumentStore` | Immutable, numbered, SHA-256-hashed versions on the private disk. Allowed types: PDF, JPEG, PNG, DOCX, XLSX; maximum 25 MB. |
| Document downloads | `/documents/versions/{id}/download` needs a 5-minute signed URL and a `DocumentPolicy` check. Content whose hash no longer matches is refused, and grants and denials are audited. |
| `DocumentPolicy` | Same agency only. Confidential documents are limited to the author and auditors. Sealed bids are unreadable by anyone until `unsealed_at` is set at authorized opening. |
| `Regulatory\RuleBook` | Returns only approved rule versions, in effect on the transaction's governing date. This supports the legacy regime (L-15). |
| `ApproveRuleVersion` / `RetireRuleVersion` | Approval needs `regulatory_rule.approve`, a reviewer other than the preparer, a legal reference, an approved interpretation, a test reference, and no overlap with another approved version. Approved versions are frozen. |

## Master data screens
| Group | Screens | Managed by |
| --- | --- | --- |
| Organization | Approval Workflows (with Steps) | System administrators. A workflow that has been used is frozen; publish a new one with a later effective date. |
| Budget master data | Fund Sources, Account Codes | `budget.master_data` (Budget Officer) |
| Catalog | Items, Item Categories, Units of Measure, Warehouses (with locations) | `master_data.manage` (Supply Officer) |
| Regulatory | Regulatory Sources, Rule Versions (approve and retire actions) | `regulatory_rule.manage` (Procurement Officer) drafts; `regulatory_rule.approve` (new **Legal Reviewer** role) approves |

Non-administrators see and maintain only their own agency's records. Auditors can read everything.

## Schema changes
These were edited in place, per the roadmap's schema change policy:
- new table `reference_sequences`
- `regulatory_rule_versions.prepared_by`
- `documents.unsealed_at`

## Tests
- `tests/Feature/Shared/*`: reference numbers, transitions, approval engine, document store and downloads, rule book.
- `tests/Feature/Filament/MasterDataUiTest.php`: the master data screens and their access rules.
- `tests/Concurrency/ReferenceNumbersConcurrencyTest.php`: 6 forked workers on separate connections request 90 numbers with none duplicated. The test fails if the row lock is removed.
- The `concurrently()` helper in `tests/Pest.php` is reusable for the budget and stock race tests in later phases.

## Open items
- Malware scanning of uploads (doc 14) is not wired in. Add a scanner before accepting files from outside the agency.
- No document upload screen yet. Uploads will be attached to records in the phases that own them, starting with phase 3.
- Agency inputs still needed: the account code (UACS) list, fund sources, and the approval routes from the office orders.
