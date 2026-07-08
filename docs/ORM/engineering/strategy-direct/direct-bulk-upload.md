---
title: "Direct Strategy: Bulk Upload"
audience: [developer, implementer]
slug: direct-bulk-upload
source_files:
  - "src/Services/BulkMemberUploadService.php"
  - "src/WicketORM/templates-partials/process/bulk-upload-members.php"
---

# Direct Strategy: Bulk Upload

Bulk upload is available in direct mode when:

- `presentation.member_list.show_bulk_upload = true`
- the actor can add members
- a target organization membership can be resolved

## Current Runtime Behavior

- uploads go through `templates-partials/process/bulk-upload-members.php`
- files are queued into `BulkMemberUploadService`
- processing runs in WP-Cron batches
- duplicate file hashes are rejected
- row processing reuses strategy-aware member add logic
