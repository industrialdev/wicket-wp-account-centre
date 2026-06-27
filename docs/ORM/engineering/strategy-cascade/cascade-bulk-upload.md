# Cascade Strategy: Bulk Upload

Bulk upload is available in cascade mode when:

- `presentation.member_list.show_bulk_upload = true`
- the actor can add members
- a target organization membership can be resolved

## Current Runtime Behavior

- uploads are queued through `BulkMemberUploadService`
- processing runs in WP-Cron batches
- duplicate file hashes are blocked
- each row is routed back through strategy-aware add logic
