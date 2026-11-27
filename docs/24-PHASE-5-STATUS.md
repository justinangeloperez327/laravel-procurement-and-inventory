# Phase 5 — Supplier registry and compliance

Status: **done**. Built ahead of phase 4; the roadmap allows phase 5 to run in parallel because it depends only on phase 2. Covers L-11 (qualifications, conflict of interest, beneficial ownership) and L-14 (sanctions).

## Supplier registry
| Step | Rules |
| --- | --- |
| Register | `supplier.manage` (BAC Secretariat, Procurement Officer). New suppliers start **pending**. The TIN is normalized to digits and must be unique per agency. |
| Eligibility documents | Uploaded with the file (hashed, private storage, signed downloads from phase 2). Types that expire require an expiry date. Documents are **verified by a different person** (`supplier.verify`). Expired documents cannot be verified. Verified documents are frozen; a renewal is a new document. |
| Beneficial ownership | Declarations with a percentage or basis of control. The total may never exceed 100%, checked exactly in PostgreSQL. Declarations are verified by `supplier.verify` and frozen once verified. |
| Activate | `supplier.verify`. Requires the required documents to be verified and unexpired, ownership declared and verified, and no blocking flags. |
| Deactivate, reopen | Deactivation needs a reason. A reopened supplier returns to pending and must be verified again. |

Status is a state machine (`SupplierStatus` implements `HasTransitions`): pending, active, suspended, blacklisted, inactive. Every change goes through `TransitionStatus` with a recorded reason.

## Eligibility (`SupplierEligibility`)
`check($supplier, $date)` lists every reason a supplier cannot take part on that date:
- not verified, or inactive
- a suspension or blacklisting in force (started, not ended, not lifted)
- a required document missing, unverified or expired
- no beneficial ownership declaration
- an open blocking compliance flag

It reads the records directly, so sanctions and expiries take effect on their date even before the daily sweep runs. **Phases 6–8 must call `assert()` before inviting, short-listing, evaluating or awarding a supplier** (docs/16: "sanctioned or ineligible supplier cannot be invited or awarded").

The required documents are configured in `SupplierDocumentTypes::REQUIRED`: PhilGEPS registration, business permit and tax clearance. This is a starting set to confirm against the current IRR.

## Sanctions
Recorded with `supplier.sanction` (HoPE, Procurement Officer), with the issuing authority, order reference, reason and effective period. A sanction is never edited or deleted; it is lifted with a reason.
- Recording a sanction updates the supplier's status and raises a **blocking** compliance flag.
- That flag stays until a compliance reviewer clears it, even after the sanction ends.

## Compliance
- **Flags** (`ComplianceFlags`): one open flag per subject and type, so there are no duplicates. Severity is info, warning or blocking. Review with `compliance.review` (HoPE, BAC Chair) and notes; decisions are reviewed, dismissed or escalated. The person who raised a flag cannot review it.
- **Daily sweep** (`gpims:supplier-compliance-sweep`, scheduled at 00:30): moves supplier status as sanctions start and end, and raises *expiring* (info, 30 days before) and *expired* (warning) document flags.
- **Conflict-of-interest declarations**: any user can declare against a project and/or a supplier. A declared conflict raises a flag: a warning if the user recused, blocking otherwise. A reviewer other than the declarant reviews it. Later phases use `ConflictDeclarations::mustRecuse()` and `hasDeclared()` to keep conflicted users out of evaluation and award.

## Screens
- **Suppliers:** list, register, a view page with a live "Eligibility today" panel, and tabs for documents (upload, verify, download), beneficial owners, sanctions and contacts.
- **Compliance:** the Compliance Flags queue (with an open-count badge and a review action), and Conflict of interest (users see their own declarations; reviewers see the agency's).

## Schema changes
These were edited in place:
- `suppliers.status` now defaults to `pending`
- `supplier_documents.recorded_by`
- `supplier_beneficial_owners.verified_by`
- `supplier_sanctions.lifted_by`
- `conflict_declarations.review_notes`

## Tests
- `tests/Feature/Suppliers/*`: registry, eligibility, sanctions, sweep and conflicts.
- `tests/Feature/Filament/SupplierUiTest.php`: the screens.

## Open items
- Confirm the required document set and PhilGEPS registration levels against the current IRR and GPPB issuances.
- Blacklisting is recorded per agency. Importing the government-wide GPPB blacklist needs an authorized data source (phase 13).
- Supplier performance ratings (`supplier_performances`) come with contracts in phase 8.
