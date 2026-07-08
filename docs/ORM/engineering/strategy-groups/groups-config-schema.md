---
title: "Groups Strategy: Config Schema"
audience: [developer, implementer]
slug: groups-config-schema
source_files:
  - "src/Config/OrgManConfig.php"
---

# Groups Strategy: Config Schema

Groups mode uses the `groups` namespace in shared config.

## Relevant Keys

- `membership.strategy = groups`
- `groups.matching.tag_name` — default `Roster Management`
- `groups.matching.tag_case_sensitive` — default `false`
- `groups.roles.management` — managing role slugs (default `['president', 'delegate', 'alternate_delegate', 'council_delegate', 'council_alternate_delegate', 'correspondent']`)
- `groups.roles.roster` — roster role slugs (default `['member', 'observer']`)
- `groups.roles.member` — default `member`
- `groups.roles.observer` — default `observer`
- `groups.roles.seat_limited` — default `['member']`
- `groups.list.page_size` — default `10`
- `groups.list.member_page_size` — default `10`
- `groups.additional_info.key` / `value_field` / `fallback_to_org_uuid`
- `groups.removal.mode` — `end_date` (default) or `delete`
- `groups.removal.end_date_format` — default `Y-m-d\TH:i:s\Z`
- `groups.removal.end_date_anchor` — inherits `removal.end_date_anchor` (`action_time` or `day_start_utc`)
- `groups.presentation.enable_group_profile_edit`
- `groups.presentation.use_unified_member_list`
- `groups.presentation.use_unified_member_view`
- `groups.presentation.show_edit_permissions`
- `groups.presentation.search_clear_requires_submit`
- `groups.presentation.editable_fields`

## Shared Access Flags (also apply in groups mode)

- `access.permissions.prevent_owner_removal`
- `access.permissions.owner_removal_requires_membership_owner_role`

## Contacts

The contacts roster (`contacts.*`) is strategy-agnostic and can be enabled alongside groups mode.