---
title: "Frontend"
audience: [developer]
source_files: ["src/Helpers/TemplateHelper.php", "src/WicketORM/templates/", "src/WicketORM/templates-partials/", "assets/"]
---

# Frontend

## Runtime Surfaces

The library renders into WordPress account pages with these slugs:

- `organization-management`
- `organization-profile`
- `organization-members`
- `organization-members-bulk`
- `organization-contacts` (only when `contacts.enabled = true`)
- `supplemental-members`

Legacy ACC compatibility slugs (routed to the same templates):

- `org-management`
- `org-management-profile`
- `org-management-members`
- `org-management-roster`

The slug-to-template map is defined in `OrgMan::getContentMap()`.

## Templates

- page templates live in `src/WicketORM/templates/`
- reusable partials live in `src/WicketORM/templates-partials/`
- mutating process handlers live in `src/WicketORM/templates-partials/process/`

Current process handlers:

- `process/add-member.php`
- `process/remove-member.php`
- `process/add-contact.php`
- `process/remove-contact.php`
- `process/bulk-upload-members.php`
- `process/add-group-member.php`
- `process/remove-group-member.php`
- `process/update-group.php`
- `process/update-permissions.php`
- `process/initiate-member-export.php`

## Hypermedia Endpoint

`TemplateHelper` exposes partials through:

- `?action=hypermedia&template=...`

It also:

- registers query vars
- normalizes `org_id` to `org_uuid`
- loads partial templates from the library safely

## Assets

Shipped under the plugin root, not inside `src/WicketORM/`:

- main stylesheet: `assets/css/orm-static.css`
- helper scripts in `assets/js/`:
  - `datastar.js` — bundled Datastar runtime used for partial refreshes
  - `datastar-error-handler.js`
  - `orm-notifications.js`
  - `orm-content-behaviors.js`
  - `wicket-acc-main.js` / `wicket-acc-admin-main.js` / `wicket-acc-legacy.js` (account-centre shell glue)

## Config Flags That Affect Rendering

- `presentation.organization_list.*`
- `presentation.organization_details.*`
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
- `contacts.enabled` (toggles `organization-contacts` page rendering)
- `contacts.presentation.page_size`

## Current Limits

- membership-cycle mode reuses shared member pages; it does not ship a cycle-tab UI or cycle resolver
- bulk upload UI is shared and only appears when `presentation.member_list.show_bulk_upload = true`
- the contacts roster is rendered on its own page; it does not appear inline on the members list