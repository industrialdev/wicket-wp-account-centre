---
title: "Architecture"
audience: [developer]
php_class: WicketORM\OrgMan
source_files: ["src/OrgMan.php", "src/Config/OrgManConfig.php", "src/Services/", "src/Controllers/", "src/Helpers/TemplateHelper.php"]
---

# Architecture

`WicketORM\` is the WordPress code inside `wicket-wp-account-centre` that injects roster-management UI into account pages and routes mutations through service classes and Datastar-oriented template endpoints.

## Layout

Everything lives under `src/WicketORM/`:

```
src/WicketORM/
  OrgMan.php                  singleton entrypoint
  compat.php                  legacy shims
  Config/OrgManConfig.php     canonical config tree
  Controllers/                REST controllers
  Services/                   business logic + strategies
  Helpers/                    template + permission + GF helpers
  templates/                  page-level account templates
  templates-partials/         partials + process handlers
  BulkImport/                 bulk CSV upload parser
```

## Core Runtime Pieces

- `OrgMan` (singleton)
  - loads config
  - instantiates services and controllers
  - injects content into supported account pages (see `getContentMap()`)
  - enqueues CSS and JS assets
  - handles WooCommerce additional-seats order processing (single-SKU and multi-tier)
- `Config/OrgManConfig`
  - defines the full default config tree
  - all site overrides are expected through `wicket/org-roster/config` and (compat) `wicket/acc/orgman/config`
- `Services/*`
  - business logic for organizations, members, memberships, groups, contacts, documents, subsidiaries, permissions, bulk upload, additional seats (single-SKU + multi-tier), member exports, and engagement data
- `Services/Strategies/*`
  - roster mutation behavior for `direct`, `cascade`, `groups`, and `membership_cycle`
- `Helpers/TemplateHelper`
  - exposes hypermedia template endpoints under `?action=hypermedia&template=...`
  - normalizes `org_id` to `org_uuid`
- `Helpers/PermissionHelper`
  - centralizes role checks: org management, contact management, owner removal guards

## Page Injection

`OrgMan` injects library content on these page slugs:

- `organization-management` → `templates/content-organization-index.php`
- `organization-profile` → `templates/content-organization-profile.php`
- `organization-members` → `templates/content-organization-members.php`
- `organization-members-bulk` → `templates/content-organization-members-bulk.php`
- `organization-contacts` → `templates/content-organization-contacts.php` (when `contacts.enabled = true`)
- `supplemental-members` → `templates/content-supplemental-members.php`

Legacy ACC slug compatibility (still routed to the same templates):

- `org-management`
- `org-management-profile`
- `org-management-members`
- `org-management-roster`

The slug-to-template map is defined in `OrgMan::getContentMap()`.

## Mutation Paths

User-facing mutations can go through either registered REST routes or the template/hypermedia layer in `templates-partials/process/`.

- controller classes in `Controllers/` are registered by `OrgMan::registerApiRoutes()`
- process handlers in `templates-partials/process/` handle hypermedia requests:
  - `add-member.php`
  - `remove-member.php`
  - `add-contact.php`
  - `remove-contact.php`
  - `bulk-upload-members.php`
  - `add-group-member.php`
  - `remove-group-member.php`
  - `update-group.php`
  - `update-permissions.php`
  - `initiate-member-export.php`

## Frontend Model

- server-rendered HTML
- Datastar-compatible partial refreshes and SSE responses
- static CSS in `assets/css/orm-static.css`
- JS helpers in `assets/js/` (datastar error handler, notifications, content behaviors, plus bundled Datastar)

The library supports both legacy member templates and unified member templates. Selection is config-driven (`presentation.member_view.use_unified`, `presentation.member_list.use_legacy_list`).

## Integrations

- WordPress: hooks, page injection, templates, query vars, nonces
- Wicket/MDP helpers: person, organization, membership, role, and group data
- WooCommerce: additional seats checkout (single-SKU and multi-tier) and post-order seat updates per tier
- Gravity Forms: additional seats form capture, tier-slug handoff, and checkout redirect

## Current Constraints

- no bundled automated test suite in this package
- membership-cycle mode exists for mutation scoping, but the library does not currently ship a cycle resolver or cycle-tab UI layer
- contacts roster is relationship-only; it does not require an active membership and is governed by `contacts.*` config rather than by `membership.strategy`