---
title: "Architecture"
audience: [developer]
php_class: WicketORM\OrgMan
source_files: ["src/OrgMan.php", "src/Config/OrgManConfig.php", "src/Services/", "src/Helpers/TemplateHelper.php"]
---

# Architecture

`wicket-lib-org-roster` is a WordPress library that injects roster-management UI into account pages and routes mutations through service classes and Datastar-oriented template endpoints.

## Core Runtime Pieces

- `src/OrgMan.php`
  - singleton entrypoint
  - loads config
  - instantiates services and controllers
  - injects content into supported account pages
  - enqueues CSS and JS assets
  - handles WooCommerce additional-seats order processing
- `src/Config/OrgManConfig.php`
  - defines the full default config tree
  - all site overrides are expected through `wicket/org-roster/config`
- `src/Services/*`
  - business logic for organizations, members, memberships, groups, documents, subsidiaries, permissions, bulk upload, additional seats, member exports, and engagement data
- `src/Services/Strategies/*`
  - roster mutation behavior for `direct`, `cascade`, `groups`, and `membership_cycle`
- `src/Helpers/TemplateHelper.php`
  - exposes hypermedia template endpoints under `?action=hypermedia&template=...`
  - normalizes `org_id` to `org_uuid`
- `templates/` and `templates-partials/`
  - server-rendered account-page views and process handlers

## Page Injection

`OrgMan` injects library content on these page slugs:

- `organization-management`
- `organization-profile`
- `organization-members`
- `organization-members-bulk`
- `supplemental-members`

These map to files in `templates/`.

## Mutation Paths

User-facing mutations can go through either registered REST routes or the template/hypermedia layer in `templates-partials/process/`.

- controller classes in `src/Controllers/` are registered by `OrgMan::registerApiRoutes()`
- process handlers in `templates-partials/process/` handle hypermedia requests

## Frontend Model

- server-rendered HTML
- Datastar-compatible partial refreshes and SSE responses
- static CSS in `public/css/modern-orgman-static.css`
- small JS helpers in `public/js/`

The library supports both legacy member templates and unified member templates. Selection is config-driven.

## Integrations

- WordPress: hooks, page injection, templates, query vars, nonces
- Wicket/MDP helpers: person, organization, membership, role, and group data
- WooCommerce: additional seats checkout and post-order seat updates
- Gravity Forms: additional seats form capture and checkout redirect

## Current Constraints

- no bundled automated test suite in this package
- membership-cycle mode exists for mutation scoping, but the library does not currently ship a cycle resolver or cycle-tab UI layer
