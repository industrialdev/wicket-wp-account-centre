---
title: "Cascade Strategy: Roles And Permissions"
audience: [developer, implementer]
slug: cascade-roles-permissions
source_files:
  - "src/WicketORM/Helpers/PermissionHelper.php"
---

# Cascade Strategy: Roles And Permissions

Cascade mode uses shared permission config.

## Relevant Keys

- `access.permissions.manage_member_roles`
- `access.permissions.add_member_roles`
- `access.permissions.remove_member_roles`
- `access.permissions.prevent_owner_removal`
- `access.permissions.owner_removal_requires_membership_owner_role`
- `access.permissions.relationship_grants.enabled`
- `access.permissions.relationship_grants.roles_by_type`

## Owner Removal

Owner removal in cascade mode goes through `PermissionHelper::guardOwnerRemoval()`:

- `access.permissions.prevent_owner_removal = true` blocks removal of `membership_owner` users entirely.
- `access.permissions.owner_removal_requires_membership_owner_role = true` requires the actor to also hold `membership_owner` before they can remove another `membership_owner`.