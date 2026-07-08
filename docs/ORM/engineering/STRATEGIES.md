---
title: "Strategies"
audience: [developer, implementer]
php_class: WicketORM\Services\MemberService
source_files: ["src/Services/Strategies/", "src/Services/MemberService.php"]
---

# Strategies

Strategy selection is driven by `membership.strategy`. The strategy decides how add, remove, and bulk mutations are routed through the strategy classes in `src/Services/Strategies/`.

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
- contacts roster is strategy-agnostic; it is governed by `contacts.*` config, not by `membership.strategy`

## Current Limits

- `membership_cycle` does not ship a resolver or cycle-tab UI
- additional-seats processing stores membership context, but the package should not be described as fully cycle-scoped across every UI surface
- groups mode has its own member-management shape and does not reuse non-groups seat semantics
- multi-tier additional seats (`tier_mode`) work in any strategy, but the documented exemplar is `membership_cycle` (ESCRS)

## Owner Removal

Removal of a member who holds the `membership_owner` role is gated by two independent flags:

- `access.permissions.prevent_owner_removal` (default `false`) — when `true`, blocks owner removal entirely.
- `access.permissions.owner_removal_requires_membership_owner_role` (default `false`) — when `true`, only users who also hold `membership_owner` can remove another `membership_owner`.

Both flags are strategy-agnostic. They live on `access.permissions.*` rather than per-strategy.

## Process Handlers

The strategy classes are invoked by hypermedia process handlers in `src/WicketORM/templates-partials/process/`:

- `add-member.php`
- `remove-member.php`
- `bulk-upload-members.php`
- `add-contact.php`
- `remove-contact.php`
- `add-group-member.php`
- `remove-group-member.php`
- `update-group.php`
- `update-permissions.php`

Each handler resolves `org_id` -> `org_uuid` (via `TemplateHelper`) and calls the strategy through `MemberService`.