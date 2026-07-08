---
title: "Groups Strategy: Roles And Permissions"
audience: [developer, implementer]
slug: groups-roles-permissions
source_files:
  - "src/Services/Strategies/GroupsStrategy.php"
---

# Groups Strategy: Roles And Permissions

## Managing Roles

Default `groups.roles.management`:

- `president`
- `delegate`
- `alternate_delegate`
- `council_delegate`
- `council_alternate_delegate`
- `correspondent`

## Roster Roles

Default `groups.roles.roster`:

- `member`
- `observer`

## Seat-Limited Roles

Default `groups.roles.seat_limited`:

- `['member']`

Seat caps are enforced per role in this list.

## Owner Removal

Shared `access.permissions.prevent_owner_removal` and `access.permissions.owner_removal_requires_membership_owner_role` apply in groups mode via `PermissionHelper::guardOwnerRemoval()`.

Only actors with an allowed managing role can use group mutation actions.