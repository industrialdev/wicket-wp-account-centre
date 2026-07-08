---
title: "Direct Strategy: Add / Remove"
audience: [developer, implementer]
slug: direct-add-remove
source_files:
  - "src/Services/Strategies/DirectAssignmentStrategy.php"
  - "src/WicketORM/templates-partials/process/add-member.php"
  - "src/WicketORM/templates-partials/process/remove-member.php"
---

# Direct Strategy: Add / Remove

## Add

- strategy key: `direct`
- scope: organization-first
- optional context:
  - `membership_uuid` or `membership_id`
  - `relationship_type`
  - `relationship_description`
  - `roles`

Current add flow:

- create or update the person
- resolve target membership UUID
- validate explicit membership scope when provided
- create missing person-to-organization relationship
- assign membership seat
- assign base member role, configured auto-roles, and optional form roles
- log touchpoint and attempt assignment email

## Remove

- requires:
  - `org_id`
  - `person_uuid`
  - `person_membership_id` in context

Current remove flow:

- sets person-to-organization relationship `ends_at` to the action time by default
- strips org-scoped roles
- respects owner-protection config:
  - `access.permissions.prevent_owner_removal` — when `true`, blocks owner removal entirely
  - `access.permissions.owner_removal_requires_membership_owner_role` — when `true`, only users who also hold `membership_owner` can remove another `membership_owner`
- can preserve the relationship when `member_management.removal.direct.preserve_relationship = true`

The owner-removal guard lives in `WicketORM\Helpers\PermissionHelper::guardOwnerRemoval()` and is invoked from every strategy's remove path, so the same rules apply across direct, cascade, groups, and membership_cycle modes.