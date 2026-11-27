# 16 — Testing Strategy

| Test | Expected behavior |
| --- | --- |
| Unapproved APP expenditure | Block or route through lawful exception |
| Concurrent budget commitments | No overcommit |
| Ineligible procurement mode | Reject or require authorized re-evaluation |
| Related subthreshold purchases | Raise splitting review flag |
| Late bid | Apply documented deadline rule |
| Early confidential bid read | Deny and record |
| Evaluation criteria changed improperly | Block |
| Pending award-blocking protest | Stop award |
| Missing required security | Prevent relevant contract transition |
| Partial failed inspection | Only accepted quantity may post |
| Duplicate receipt | Exactly one stock posting |
| Concurrent inventory issues | Prevent overissuance |
| Invoice exceeds allowable received quantity | Flag/reject |
| Unauthorized amendment | Deny |
| Audit manipulation | Deny |
| Legacy legal regime transaction | Use properly applicable version |

Unit-test business rules; feature-test permissions/workflows; integration-test PostgreSQL locks, file storage and idempotency; acceptance-test real agency scenarios with synthetic data. Record legal requirement IDs against test cases.
