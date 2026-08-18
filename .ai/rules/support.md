---
paths:
  - app/Support/SystemAuditRecorder.php
  - app/Support/OrganizationInventoryReports.php
---

# Support

## Organization audit log is metadata-only
Audit rows are metadata only (action, actor snapshot, target type/id, summary name/email/domain/asset tag, IP). Never copy secrets, notes, inventory payloads, tokens, or hashes. Record when there is an authenticated user or organization API key; skip SystemAudit and non-App models. Org owners/admins view org-scoped entries via OrganizationPolicy::viewAuditLog and the organizations.audit-log page.

## Inventory reports filter decrypted payloads in PHP
Inventory reports (pending updates, missing AV, encryption, stale inventory, recovery keys, unassigned) are computed in PHP from decrypted inventory payloads. Do not query encrypted inventory_payload in SQL. Keep findings metadata-only: no recovery keys or inventory blobs in the UI.
