# 10 — Inventory Workflow

Delivery → receipt → inspection → acceptance → exactly-once posting to `stock_movements`. Rejected units must remain unavailable. Maintain ledger and optionally materialized `stock_balances` for performance, rebuilt/reconciled from ledger.

Internal requisition → approve → reserve → pick → issue → acknowledgement. Transfers track origin, in-transit and destination confirmation. Count discrepancies require investigation, authority and signed adjustment ledger events.

Movement types: accepted_receipt, issuance, return, transfer_out, transfer_in, adjustment_plus, adjustment_minus, disposal, reversal.

Test negative stock prevention, concurrency, serial uniqueness, batch/expiry rules, unit conversion and duplicate receipt handling.
