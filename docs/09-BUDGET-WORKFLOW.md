# 09 — Budget Workflow

Budget: approved allocation → reservation → obligation → disbursement. Disbursement reduces unpaid obligations; it must not be subtracted twice from available uncommitted allocation.

Suggested flow: approved PR with APP coverage → budget officer checks fiscal year/fund/account → reserve atomically → authorized award converts appropriate reservation to obligation → record invoices and payments → release remainder on closure/cancellation.

Use fixed-decimal money and DB transactions/locks; prevent racing transactions from overcommitting the same appropriation. Preserve amendment/reversal trail, source authorization and COA/DBM references. Test partial commitments and fiscal-year rollover.
