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
