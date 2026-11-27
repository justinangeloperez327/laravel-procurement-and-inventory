# Phase 1 — Identity and organization

Implements roadmap phase 1 (roles, policies, delegation, audit) against FR-01, FR-02, docs 06 and 14.

## Delivered
| Area | Implementation |
| --- | --- |
| Organization master data | `agencies`, `departments` (hierarchical, unique code per agency), `fiscal_years` (unique per agency; closed years are final). Users gain agency, department, employee number, position and `is_active`. Records are deactivated, never deleted. |
| Roles and permissions | `App\Domain\Identity\RoleCatalog` holds the doc 06 roles and scoped permission strings. `php artisan gpims:sync-roles` (also run by `db:seed`) syncs them idempotently. The catalog is read-only in the UI. |
| Scoped authorization | `Authorizer` resolves a permission for a user and optional department. Agency-wide assignments cover all departments of that agency only; department assignments cover only that department. `Gate::allows('award.approve', $department)` and `$user->hasPermission()` use it. |
| Admin is not authority | `is_admin` (technical administrator, not mass assignable, not in any form) grants identity administration only, never statutory permissions. Administrators cannot grant roles to themselves. |
| Segregation of duties | Grants are refused for incompatible pairs: HoPE vs BAC chair/member/secretariat/TWG; receiving vs inspection; accounting vs cashier; auditor vs every transactional role. |
| Grant history | `role_assignments` are append-only: grantor, timestamp, basis, optional expiry, revoker, revocation reason. |
| Delegation | `delegations` are time-boxed (mandatory end, DB check), same-agency, of a single permission held *directly* by the delegator, in the same or narrower scope. No re-delegation; they lapse when the delegator loses the authority or is deactivated; segregation rules apply to the delegate; they are revocable with a reason. |
| Audit | `audit_logs` are append-only, enforced by PostgreSQL triggers (UPDATE/DELETE/TRUNCATE rejected) and the model. Master data changes (old/new values; passwords excluded), grants, revocations, delegations, sign-in, sign-out and failed sign-in are recorded with actor, IP and user agent. |
| Filament | Organization: Agencies, Departments, Fiscal Years. Access control: Users (with a Roles relation manager for grant and revoke), Roles (read-only), Delegations (self-service "Delegate my authority"), Audit Logs (read-only, excluded from global search). Panel access requires an active user who is an administrator or holds an active role. Auditors get read-only views. |

## Tests
`tests/Unit/RoleCatalogTest.php`, `tests/Feature/Identity/*`, `tests/Feature/Audit/AuditLogTest.php`, `tests/Feature/Filament/AccessControlUiTest.php`. They cover the doc 14 threat cases "stolen approver access" (expiry, revocation, deactivation), "altered audit log" and "cross-department exposure".

## Open items needing agency approval before production
- Confirm the role-to-permission matrix, incompatible-role pairs and any maximum delegation length against the agency's office orders.
- MFA for privileged users (doc 14) is not implemented yet. It needs a decision on the factor (TOTP vs. email) and on recovery.
- A tamper-evident audit export (hash chaining or external signing) is still outstanding.
