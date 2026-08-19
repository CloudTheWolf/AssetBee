---
paths:
  - app/Support/SystemAuditRecorder.php
  - app/Support/OrganizationInventoryReports.php
  - app/Support/SimplePdf.php
---

# Support

## Organization audit log is metadata-only
Audit rows are metadata only (action, actor snapshot, target type/id, summary name/email/domain/asset tag, IP). Never copy secrets, notes, inventory payloads, tokens, or hashes. Record when there is an authenticated user or organization API key; skip SystemAudit and non-App models. Org owners/admins view org-scoped entries via OrganizationPolicy::viewAuditLog and the organizations.audit-log page.

## Inventory reports filter decrypted payloads in PHP
Inventory reports (pending updates, missing AV, encryption, stale inventory, recovery keys, unassigned) are computed in PHP from decrypted inventory payloads. Do not query encrypted inventory_payload in SQL. Keep findings metadata-only: no recovery keys or inventory blobs in the UI.

## PDFs use branded SimplePdf headers
PDFs use SimplePdf with a black header containing public/img/logo.png. Inventory report PDFs are metadata-only: names, types, assignment, and finding summaries; never recovery keys or inventory payloads.

## Unknown antivirus freshness is not out of date
Linux and macOS collectors often omit antivirus.upToDate (null) because there is no Security Center definition bit. Treat enabled products as protected unless upToDate is explicitly false. Only Windows-style false should produce “Antivirus is out of date.”
