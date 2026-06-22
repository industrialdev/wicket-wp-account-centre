# Groups Strategy: Add / Remove

## Add

- strategy key: `groups`
- requires `group_uuid`
- actor must pass group access checks
- requested role must be in `groups.roster_roles`

Current add flow:

- resolve or create person
- ensure person-to-organization relationship exists
- build org association payload for `custom_data_field`
- create the group-member record

## Remove

- requires:
  - `group_uuid`
  - `person_uuid`
- remove behavior depends on `groups.removal.mode`
  - `end_date`
  - `delete`
