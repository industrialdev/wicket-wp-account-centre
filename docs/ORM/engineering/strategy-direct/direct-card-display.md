---
title: "Direct Strategy: Card Display"
audience: [developer, implementer]
slug: direct-card-display
source_files:
  - "src/WicketORM/templates-partials/card-organization-direct-cascade.php"
  - "src/WicketORM/templates-partials/members-list.php"
---

# Direct Strategy: Card Display

Direct mode uses the shared non-groups organization UI.

## Current Display Behavior

- top-level screen is organization-centric
- heading remains `Manage Organizations`
- organization cards link to profile and member screens
- member list and member view use shared templates
- unified list/view is controlled by shared `presentation.member_list.*` and `presentation.member_view.*` config
