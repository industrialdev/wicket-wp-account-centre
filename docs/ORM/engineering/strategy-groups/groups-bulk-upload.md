# Groups Strategy: Bulk Upload

Bulk upload is available in groups mode when:

- `presentation.member_list.show_bulk_upload = true`
- `group_uuid` is present
- current user can manage the group

## Current Runtime Behavior

- upload requests go through the shared process handler
- the handler validates group access first
- rows are queued into `BulkMemberUploadService`
- processing runs in background WP-Cron batches
- row mutations use the active groups strategy
