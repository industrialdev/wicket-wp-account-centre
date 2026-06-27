---
title: "Design"
audience: [developer]
source_files: ["src/OrgMan.php", "src/Helpers/TemplateHelper.php", "templates/", "templates-partials/"]
---

# Design

The library is designed around WordPress account pages, server-rendered partials, and low-JS progressive enhancement.

## Design Goals

- keep roster flows in PHP templates
- support multiple roster strategies without changing page slugs
- let sites change behavior through config instead of forks
- integrate additional-seats checkout without a separate frontend app

## Current UI Shape

- organization entry pages are injected into account-page content
- member management can render in legacy or unified layouts
- groups mode uses group-centric cards and member views
- non-groups modes use organization-centric cards and member views

## Reactive Layer

- Datastar-compatible partial responses are used for process handlers
- hypermedia requests are routed through `TemplateHelper`
- small JS files provide notification and content behavior helpers

## Current Constraints

- no client-side cycle resolver exists for membership-cycle mode
- no dedicated SPA or build step exists in this package
