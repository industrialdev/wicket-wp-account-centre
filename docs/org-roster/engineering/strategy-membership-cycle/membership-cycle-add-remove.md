# Membership Cycle Strategy: Add / Remove

## Add

- strategy key: `membership_cycle`
- requires explicit `membership_uuid` or `membership_id` in context
- validates that the membership belongs to the target organization
- delegates the add flow to direct strategy after validation

## Remove

- requires:
  - `membership_uuid` or `membership_id`
  - `person_membership_id`
- removal ends the selected person-membership assignment for that cycle
- owner removal is blocked by default through `membership.cycle.prevent_owner_removal`
