# Groups Strategy: Logic

Groups mode is group-member management, not organization-membership assignment.

## Current Logic

- determine whether the actor belongs to and can manage the target group
- infer org association from group context
- create or resolve the target person
- ensure org relationship exists
- create or close a `group_members` record

Seat-limited roles are enforced per configured `groups.seat_limited_roles`.
