---
title: "Cascade Strategy: Logic"
audience: [developer, implementer]
slug: cascade-logic
source_files:
  - "src/Services/Strategies/CascadeStrategy.php"
  - "src/Services/MemberService.php"
---

# Cascade Strategy: Logic

Cascade strategy keeps the library aligned with legacy cascade-oriented member-management behavior.

## Current Logic

- create or resolve person
- resolve membership for the target organization
- ensure person-to-organization relationship exists
- perform seat assignment and role side effects through the current cascade path
- emit notifications and touchpoints where available
- on remove, set person-to-organization relationship `ends_at` to the action time and strip org-scoped roles
- owner-removal guard is enforced via `PermissionHelper::guardOwnerRemoval()`