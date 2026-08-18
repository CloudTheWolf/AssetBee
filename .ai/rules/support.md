---
paths:
  - app/Support/SystemAuditRecorder.php
---

# Support

## Organization audit log is metadata-only
Audit rows are metadata only (action, actor snapshot, target type/id, summary name/email/domain/asset tag, IP). Never copy secrets, notes, inventory payloads, tokens, or hashes. Record when there is an authenticated user or organization API key; skip SystemAudit and non-App models. Org owners/admins view org-scoped entries via OrganizationPolicy::viewAuditLog and the organizations.audit-log page.
