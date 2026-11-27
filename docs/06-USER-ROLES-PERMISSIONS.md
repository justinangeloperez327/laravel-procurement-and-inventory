# 06 — Roles and Permissions

Roles: System Administrator, HoPE, Department Head, End User, Planning Officer, Budget Officer, Procurement Officer, BAC Chair, BAC Member, BAC Secretariat, TWG Member, Observer, Receiving Officer, Inspection Officer, Supply Officer, Property Officer, Accounting Officer, Cashier, Auditor and future Supplier.

Use scoped permission strings: `ppmp.create`, `app.approve`, `budget.certify`, `purchase_request.submit`, `procurement_mode.review`, `bid.evaluate`, `award.approve`, `delivery.receive`, `inspection.accept`, `inventory.issue`, `asset.assign`, `audit.read`.

Enforce agency/department record scope; deny conflicted evaluation; segregate BAC recommendations from HoPE approval; distinguish receiving from independent inspection where applicable. Technical administrator privileges must not confer statutory approval authority. Record grants, delegation expiry and revocation. Auditor role is read-only.

Exact agency positions, quorum and signatures must be approved before production configuration.
