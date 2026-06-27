---
title: "Strategies"
audience: [developer, implementer]
php_class: WicketORM\Services\MemberService
source_files: ["src/Services/Strategies/", "src/Services/MemberService.php"]
---

# Strategies

Strategy selection is driven by `membership.strategy`.

## Registered Strategies

- `direct` -> `DirectAssignmentStrategy`
- `cascade` -> `CascadeStrategy`
- `groups` -> `GroupsStrategy`
- `membership_cycle` -> `MembershipCycleStrategy`

Unknown keys fall back to `cascade` in `MemberService`.

## Summary Matrix

- `direct`
  - organization-scoped roster mutation
  - resolves membership directly or from explicit context
- `cascade`
  - organization-scoped roster mutation with legacy cascade-oriented side effects
- `groups`
  - requires `group_uuid`
  - writes group membership records instead of person-membership assignments
- `membership_cycle`
  - requires explicit `membership_uuid` for mutating actions
  - delegates add to direct strategy after scope validation
  - removes by ending the selected person-membership assignment

## Shared Behavior

- add and remove calls route through `MemberService`
- permissions are still enforced outside and inside strategy logic
- bulk upload uses the same strategy layer

## Current Limits

- `membership_cycle` does not ship a resolver or cycle-tab UI
- additional-seats processing stores membership context, but the package should not be described as fully cycle-scoped across every UI surface
- groups mode has its own member-management shape and does not reuse non-groups seat semantics
