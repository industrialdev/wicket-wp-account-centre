---
title: "Groups Strategy: Card Display"
audience: [developer, implementer]
slug: groups-card-display
source_files:
  - "src/WicketORM/templates-partials/card-organization-groups.php"
  - "src/WicketORM/templates-partials/members-list-groups.php"
---

# Groups Strategy: Card Display

Groups mode changes the top-level management surface from organizations to groups.

## Current Display Behavior

- heading becomes `Manage Groups`
- `organization-management` lists active tagged group memberships
- if exactly one eligible group is found, the UI can auto-redirect to `organization-members?group_uuid=...`
- group profile editing is controlled by `groups.presentation.enable_group_profile_edit`
- unified group member list and view are on by default
