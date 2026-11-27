# 07 — Procurement Workflows

## Core sequence
1. Market scoping → PPMP → consolidated APP and HoPE-approved version.
2. Purchase request with plan linkage, budget check and authorization.
3. Procurement mode screening with legal justification and reviewer.
4. Mode-specific RFQ/solicitation, publication and bidding procedures.
5. Bid/quotation evaluation, applicable post-qualification and BAC recommendation.
6. Authorized award decision, NOA, contract/PO, appropriate securities and NTP.
7. Delivery, receipt, inspection, line-level acceptance or discrepancy.
8. Accepted goods posted into warehouse or asset register; service completion recorded.
9. Supplier invoice matching, payment reference, closure and audit.

## State machines
PR: draft → submitted → department_review → budget_review → approved → procurement → fulfilled; include returned, rejected and cancelled paths.
Procurement: proposed → mode_review → solicitation → receipt → evaluation → post_qualification → recommended → awarded → contracted → implementation → completed.
Delivery: scheduled → received → inspection_pending → accepted/partially_accepted/rejected → posted or discrepancy_followup.

## Notes
Transitions differ by procurement mode. Preserve event date, actor, attachments, rationale and prior version. Handle emergency/failure/protest exceptions explicitly, not via hidden skip buttons.
