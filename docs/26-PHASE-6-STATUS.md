# Phase 6 — Procurement projects and mode screening

Status: **done**. Covers L-04 (procurement modes), L-05 (splitting prevention) and L-06 (BAC), plus two docs/16 cases: "Ineligible procurement mode" and "Related subthreshold purchases".

## Procurement projects
- Built by `procurement.manage` holders (Procurement Officer, BAC Secretariat) from **lines of approved purchase requests** of one fiscal year. Numbered `PROJ-YYYY-NNNN`.
- The ABC is the exact SQL sum of the lines. The APP item and end-user department are taken over when they are shared by all lines.
- A line belongs to at most one active project. Lines of a cancelled or failed project can be procured again.
- Included purchase requests move to *procurement*.
- Lines can be grouped into **lots** while the project is proposed; each lot's ABC is the sum of its lines.
- Title, scope and lines freeze once mode review starts (`FreezesWhenFinal`).
- Cancellation: procurement staff may cancel before solicitation; after that only the HoPE (`procurement_mode.approve`).

## Mode screening (`ModeScreening`)
- Screens a candidate mode against the **approved, effective rule versions** linked to it (`procurement_mode_rules`), as of the date procurement started (L-15).
- No peso amounts are hardcoded. Rule versions carry machine-checkable `conditions`:
  - `abc_max` / `abc_min`: ABC limits
  - `categories`: allowed procurement categories
  - `requires_evidence`: evidence keys the justification must supply
- Unknown conditions, or rules without conditions, are shown to the reviewer as items to confirm.
- **Competitive bidding** (the default method) needs no rule. **Every alternative mode needs at least one approved rule in effect**; otherwise it cannot be used. Draft and retired versions never count.

## Mode selection (`ModeSelection`)
1. **Propose:** a mode, a justification and evidence. A blocking rule failure refuses the proposal.
2. **BAC review** (`procurement_mode.review`): endorse, return or reject.
   - Endorsing an **alternative** mode needs a BAC resolution of type *recommend mode* for the project.
   - The project is re-screened at endorsement.
3. **HoPE decision** (`procurement_mode.approve`) for alternative modes: approve, return or reject. Competitive bidding becomes final at BAC endorsement.
4. Once final, the mode is set on the project, which moves to **solicitation**. A return or rejection sends the project back to *proposed* for a new proposal.

Reviewers must have **declared** for the project and must not have declared a conflict (phase 5). The preparer cannot review, and the BAC reviewer cannot also give the HoPE approval. Every proposal keeps its screening snapshot.

## Splitting review (`SplittingCheck`, L-05)
When an alternative mode with a ceiling is proposed, other active projects of the same agency and fiscal year that share an APP item or item category and also use an alternative mode are added up. If the combined ABC exceeds the ceiling, a **possible splitting** warning flag goes to the compliance queue.

## BAC (L-06)
- **Constitution** by the HoPE (`bac.constitute`) under an office order. Each designation must match the member's role (Chair, Member, Secretariat). Only one chairperson at a time; designations have start and end dates.
- **Meetings** are scheduled by the secretariat or chair (`bac.meeting`). Recording attendance closes the meeting and computes **quorum**: a majority of the voting members serving on that date, with the chair or vice-chair present. Observers are recorded by name. A held meeting is frozen.
- **Resolutions** (`bac.recommend`) can be adopted only at a held meeting with quorum, by a voting member who was present. They are numbered `BACRES-YYYY-NNNN` and cannot be edited.

## Schedule
- **Deadlines:** an extension adds a new row linked to the previous one (with a reason). Deadlines are never edited, and the current deadline is the latest in each chain.
- **Publications:** recorded per channel (PhilGEPS, agency website, conspicuous place, newspaper).
- **Events:** pre-procurement and pre-bid conferences and similar.

## Screens
- **Procurement → Procurement Projects:** new project from approved lines; *Propose procurement mode* (with live screening in the dialog); tabs for mode proposals (BAC review and HoPE decision), lines, lots, deadlines (set and extend) and publications.
- **Procurement → Bids and Awards Committees:** constitute; members (designate and end); meetings (schedule, record attendance, adopt resolution); resolutions.

## Schema changes
These were edited in place:
- `procurement_justifications`: `status`, `screening_result`, `approved_by`, `approved_at`, `bac_resolution_id` (added in the bidding migration because of table order)
- `procurement_deadlines.created_by`
- `bac_resolutions.recorded_by`

## Tests
- `tests/Feature/Procurement/*` (29 tests): projects, lots, deadlines, mode screening and selection, splitting, BAC quorum and resolutions.
- `tests/Feature/Filament/ProcurementUiTest.php`.

## Open items
- **Agency and legal input:** the rule versions and conditions for each alternative mode (thresholds, required evidence) must be prepared and approved by the legal reviewer before alternative modes can be used. Demo values are test fixtures only.
- Confirm the quorum rule against the current IRR and the BAC's internal rules.
- PhilGEPS posting is recorded manually; automated posting needs an authorized integration (phase 13).
