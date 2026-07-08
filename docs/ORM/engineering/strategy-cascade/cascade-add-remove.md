---
title: "Cascade Strategy: Add / Remove"
audience: [developer, implementer]
slug: cascade-add-remove
source_files:
  - "src/Services/Strategies/CascadeStrategy.php"
  - "src/WicketORM/templates-partials/process/add-member.php"
  - "src/WicketORM/templates-partials/process/remove-member.php"
---

# Cascade Strategy: Add / Remove

## Add

- strategy key: `cascade`
- scope: organization-first
- optional context:
  - `relationship_type`
  - `relationship_description`
  - `roles`

Current add flow:

- resolve or create person
- resolve organization membership UUID
- create org relationship when missing
- assign seat through the cascade-oriented flow
- assign configured roles
- emit notifications and touchpoints when available

## Remove

- requires:
  - `org_id`
  - `person_uuid`
  - `person_membership_id` in context

Current remove flow:

- sets person-to-organization relationship `ends_at` to the action time by default
- strips org-scoped roles
- applies the same owner-removal guard as direct mode:
  - `access.permissions.prevent_owner_removal`
  - `access.permissions.owner_removal_requires_membership_owner_role`