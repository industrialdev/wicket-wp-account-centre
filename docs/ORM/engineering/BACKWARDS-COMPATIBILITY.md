---
title: "Backwards Compatibility"
audience: [developer]
php_class: WicketORM\OrgMan
source_files: ["src/OrgMan.php", "src/Config/OrgManConfig.php"]
---

# Backwards Compatibility

Released behavior in this library is expected to stay stable unless a breaking change is explicitly approved.

## Current Compatibility Rules

- keep `membership.strategy` defaulting to `direct`
- keep additive config keys opt-in
- do not silently change permission defaults
- keep `WicketORM\OrgMan::get_instance()` as the theme-facing alias
- keep internal PHP APIs on camelCase naming
- keep upstream WordPress, WooCommerce, and Wicket helper names unchanged when they use underscores
- never silently flip a default flag that expands access (e.g. `prevent_owner_removal`, `contacts.enabled`)

## Additive Defaults Already in the Library

The following areas are additive and safe by default because they ship disabled or empty:

- `membership.resolution.prefer_current_cycle`
- `access.permissions.role_only_management_access.*`
- `access.permissions.owner_removal_requires_membership_owner_role` (default `false`; new flag, ships disabled)
- `presentation.member_list.show_bulk_upload`
- `presentation.member_list.display_roles.allowlist`
- `presentation.member_list.display_roles.denylist`
- `presentation.member_list.remove_policy_callout.*`
- `membership.cycle.*`
- `contacts.*` (entire block, ships with `enabled = false`)
- `integrations.additional_seats.tier_mode` (default `false`)
- `integrations.additional_seats.tier_skus` (default `[]`)
- `integrations.additional_seats.tier_slug_field` (default `tier-slug`)
- `exports.*` (master switch `enabled` defaults to `false`)
- `engagement.*` (master switch `enabled` defaults to `false`)

## What Counts As Breaking

- changing default strategy behavior
- changing default permissions in a way that expands access
- removing a documented config key that exists in `OrgManConfig`
- removing the `get_instance()` bridge alias
- changing process-handler request shapes in a way that breaks existing templates
- adding a new required field to the additional-seats GF form that would break existing deployed forms
- changing the page-slug-to-template map without preserving legacy aliases (`org-management`, `org-management-profile`, `org-management-members`, `org-management-roster`)

## Migration Safety Net

`OrgMan::getInstance()` is gated by a defer-guard: if the standalone WicketORM plugin is also active, the standalone instance wins. This lets a site run with both the legacy bundled plugin and the in-account-centre library during the migration window without double-instantiation. Once the standalone plugin is removed, account-centre takes over automatically.