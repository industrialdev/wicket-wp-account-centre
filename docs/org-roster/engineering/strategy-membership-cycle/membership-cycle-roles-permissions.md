# Membership Cycle Strategy: Roles And Permissions

Membership-cycle mode has strategy-local permission keys.

## Relevant Keys

- `membership.cycle.permissions.add_member_roles`
- `membership.cycle.permissions.remove_member_roles`
- `membership.cycle.permissions.purchase_seat_roles`
- `membership.cycle.prevent_owner_removal`

## Defaults

- add: `membership_manager`
- remove: `membership_manager`
- purchase seats: `membership_owner`, `membership_manager`, `org_editor`
- owner removal blocked: `true`
