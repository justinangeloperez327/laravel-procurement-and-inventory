# Phase 7 — Solicitation, bidding and evaluation

Status: **done**. Covers L-07 (solicitation and opening), L-08 (evaluation and post-qualification) and L-12 (protests), plus these docs/16 cases: late bid, early read of a confidential bid, evaluation criteria changed improperly, and conflicted evaluator.

## Evaluation criteria
- Criteria are drafted (pass/fail or weighted rated), then **published, which locks them**.
- Locked criteria can never be edited or deleted (model guard), and no criteria can be added once an RFQ or bidding has been issued.
- Solicitation requires every criterion to be published.

## RFQ (alternative modes, e.g. small value procurement)
- An RFQ is issued with a deadline and a minimum number of quotations sought (default 3).
- Quotations are recorded **only from eligible suppliers** (phase 5), stamped with server time. Unit prices are entered per line, and totals are computed by PostgreSQL.
- Quotations after the deadline are recorded as **late** and never evaluated.
- The RFQ can be closed only after its deadline. Too few on-time quotations raises an *insufficient quotations* compliance flag. The project moves to evaluation.

## Competitive bidding
- A bidding is opened with a submission deadline and opening time; matching project deadlines are created, so extensions use the phase 6 deadline chain.
- **Receipt:** the secretariat records each bid with the server clock and a `RCPT-YYYY-NNNN` receipt number. Envelopes are stored as **sealed** documents. No amount exists in the system before opening, and the receipt details can never change.
- **Late bids:** received after the effective deadline (extensions count), recorded and **returned unopened**.
- **Withdrawal:** allowed before the deadline only; the receipt record stays, and the bidder may resubmit.
- **Opening:** only at or after the opening time (early attempts are refused and audited), at a **held BAC meeting with quorum** for the project, recorded by a voting member present.
  - The amount read from each on-time bid is recorded once.
  - The envelopes are unsealed, so authorized users can download them.
  - Before opening, any download attempt is refused and audited.

## Evaluation
- Evaluators (`bid.evaluate`) must have **declared** and have no conflict with the project **or the supplier**. Each evaluator records one immutable result per offer and criterion.
- A pass/fail criterion is met when most evaluators passed it. Rated criteria are averaged and weighted.
- **Ranking:**
  - goods and infrastructure: lowest amount among passing offers
  - consulting services: highest weighted score
- *Finish evaluation* (`bac.recommend`) requires every offer to be fully evaluated and at least one to pass. It marks offers passed/failed (or responsive/non-responsive) and moves to post-qualification.

## Post-qualification
- The best-ranked passing offer is post-qualified; if it fails, the next one is.
- A supplier that has become ineligible since evaluation (for example, sanctioned) **fails automatically**, with the reasons recorded.
- Results need findings and an independent evaluator.

## Recommendation and failure
- **Recommend award:** requires a passed post-qualification and a BAC resolution of type *recommend award*. The project becomes *recommended*.
- **Declare failure:** requires a BAC resolution of type *declare failure*. The RFQs and biddings fail, the project fails, and its purchase request lines can be procured again.

## Disputes
- **Requests for reconsideration** are filed for a participating supplier and decided by the BAC.
- **Protests** require a reconsideration the BAC denied, and are decided by the HoPE (`award.approve`).
- An open protest **blocks award**: `Disputes::blocksAward()` is the hook phase 8 must check.

## Screens
The procurement project page now has these tabs:
- Evaluation criteria (add, publish and lock)
- RFQs (issue, record quotation, close) and Quotations, for alternative modes
- Bidding (open, receive bid with envelope uploads, open bids) and Bids received (amounts show "Sealed" until opening), for competitive bidding
- Post-qualification
- Disputes (reconsideration and protests)

Header actions cover recording evaluations, finishing evaluation, post-qualifying the next offer, recommending award and declaring failure. An evaluation ranking panel appears from evaluation onwards.

## Schema changes
These were edited in place:
- columns recording who acted: on quotations, bids, criteria, protests, reconsiderations and post-qualifications
- `rfqs.closed_at`
- `biddings.issued_by`, `opened_at` and `opening_meeting_id`
- `bids.withdrawn_at`
- `quotation_items.total`: now a stored generated column
- the one-bid-per-supplier index now excludes withdrawn bids

## Tests
- `tests/Feature/Bidding/*` (17 tests).
- `tests/Feature/Filament/BiddingUiTest.php`.

## Open items
- Confirm against the current IRR:
  - the minimum number of quotations
  - the requirement of a reconsideration before a protest, and protest fees
  - the two-envelope procedure, and whether eligibility is checked before the financial envelope is opened (currently both envelopes open at one session)
  - bid security
- Bid security, and checking amounts against the ABC (bids above the ABC), are not enforced yet. Add them with the agency's rules.
- Electronic bid submission by suppliers needs the supplier portal (deferred). Today the secretariat records receipts.
