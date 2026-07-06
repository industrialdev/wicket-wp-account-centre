---
title: "Frontend"
audience: [developer]
source_files: ["src/Helpers/TemplateHelper.php", "templates/", "templates-partials/", "public/"]
---

# Frontend

## Runtime Surfaces

The library renders into WordPress account pages with these slugs:

- `organization-management`
- `organization-profile`
- `organization-members`
- `organization-members-bulk`
- `supplemental-members`

## Templates

- page templates live in `templates/`
- reusable partials live in `templates-partials/`
- mutating process handlers live in `templates-partials/process/`

Important process handlers in current use:

- `process/add-member.php`
- `process/remove-member.php`
- `process/bulk-upload-members.php`
- `process/add-group-member.php`
- `process/remove-group-member.php`
- `process/update-group.php`
- `process/update-permissions.php`

## Hypermedia Endpoint

`TemplateHelper` exposes partials through:

- `?action=hypermedia&template=...`

It also:

- registers query vars
- normalizes `org_id` to `org_uuid`
- loads partial templates from the library safely

## Assets

- main stylesheet: `assets/css/orm-static.css`
- helper scripts:
  - `assets/js/datastar-error-handler.js`
  - `assets/js/orm-notifications.js`
  - `assets/js/orm-content-behaviors.js`

## Config Flags That Affect Rendering

- `presentation.organization_list.*`
- `presentation.relationships.*`
- `presentation.member_list.use_legacy_list` (opt-in to legacy `members-list.php`; default `false`)
- `presentation.member_list.show_edit_permissions`
- `presentation.member_list.show_remove_button`
- `presentation.member_list.show_bulk_upload`
- `presentation.member_list.account_status.*`
- `presentation.member_list.remove_policy_callout.*`
- `presentation.member_view.use_unified`
- `presentation.member_view.search_clear_requires_submit`
- `presentation.member_card.fields.*`
- `groups.presentation.*`

## Current Limits

- membership-cycle mode reuses shared member pages; it does not ship a cycle-tab UI or cycle resolver
- bulk upload UI is shared and only appears when `presentation.member_list.show_bulk_upload = true`
