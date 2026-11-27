# 14 — Security Requirements

- Server-side Laravel policies and agency/department scopes on queries and commands.
- Strong authentication, MFA for privileged users, expiring delegated authority.
- No confidential bids in publicly readable storage; time-restricted document access.
- Bid secrecy until authorized opening; deadline enforcement and immutable receipt timestamps.
- Encryption in transit and at rest, signed uploads/downloads, malware scanning and document hashes.
- Segregation of duties, COI checks, access logs and tamper-resistant audit export.
- Secrets management, least-privilege database credentials, backup/restore drill, secure environment settings.
- RA 10173 privacy controls: minimize personal data, role-based disclosure, documented retention.

Threat cases: stolen approver access, bid leakage, duplicate stock posting, concurrent budget overcommit, altered audit log and cross-department exposure. Release requires security and authorization test suite.
