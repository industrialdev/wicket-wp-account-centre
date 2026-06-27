# Groups Strategy: Seats

Groups mode has different seat semantics from non-groups strategies.

## Current Behavior

- seat-limited roles are defined by `groups.seat_limited_roles`
- default limit is effectively one matching seat-limited role per org per group
- non-seat-limited roles are not blocked by that rule
