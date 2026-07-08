---
title: "Membership Cycle Strategy: Bulk Upload"
audience: [developer, implementer]
slug: membership-cycle-bulk-upload
source_files:
  - "src/Services/BulkMemberUploadService.php"
---

# Membership Cycle Strategy: Bulk Upload

Bulk upload is available in membership-cycle mode when:

- `presentation.member_list.show_bulk_upload = true`
- actor can add members
- a target `membership_uuid` is available

## Current Runtime Behavior

- uploads go through the shared process handler
- the handler passes membership context into the queued upload job
- processing runs in background WP-Cron batches
- row add behavior is strategy-aware

## Current Limits

- the package does not ship a separate cycle-specific bulk-upload config namespace
- earlier ESCRS planning notes described stricter whitelist behavior than the current shared config exposes
