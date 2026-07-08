---
title: "Groups Strategy: Add / Remove"
audience: [developer, implementer]
slug: groups-add-remove
source_files:
  - "src/Services/Strategies/GroupsStrategy.php"
  - "src/WicketORM/templates-partials/process/add-group-member.php"
  - "src/WicketORM/templates-partials/process/remove-group-member.php"
---

# Groups Strategy: Add / Remove

## Add

- strategy key: `groups`
- requires `group_uuid`
- actor must pass group access checks
- requested role must be in `groups.roles.roster`

Current add flow:

- resolve or create person
- ensure person-to-organization relationship exists
- build org association payload for `custom_data_field`
- create the group-member record

## Remove

- requires:
  - `group_uuid`
  - `person_uuid`
- remove behavior depends on `groups.removal.mode`:
  - `end_date` — set the group-member record's end date using `groups.removal.end_date_format` (default `Y-m-d\TH:i:s\Z`) and `removal.end_date_anchor` (`action_time` or `day_start_utc`)
  - `delete` — remove the group-member record outright
- owner-removal guard applies: `access.permissions.prevent_owner_removal` and `access.permissions.owner_removal_requires_membership_owner_role` are honored through `PermissionHelper::guardOwnerRemoval()`