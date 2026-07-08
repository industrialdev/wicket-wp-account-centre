---
title: "Groups Strategy: Logic"
audience: [developer, implementer]
slug: groups-logic
source_files:
  - "src/Services/Strategies/GroupsStrategy.php"
  - "src/Services/MemberService.php"
---

# Groups Strategy: Logic

Groups mode is group-member management, not organization-membership assignment.

## Current Logic

- determine whether the actor belongs to and can manage the target group
- infer org association from group context
- create or resolve the target person
- ensure org relationship exists
- create or close a `group_members` record
- on remove, end-date or delete the group-member record based on `groups.removal.mode` and `groups.removal.end_date_anchor`

Seat-limited roles are enforced per configured `groups.roles.seat_limited` (default `['member']`).

Owner removal is enforced via `PermissionHelper::guardOwnerRemoval()` so the same `access.permissions.prevent_owner_removal` and `access.permissions.owner_removal_requires_membership_owner_role` flags apply across groups mode too.