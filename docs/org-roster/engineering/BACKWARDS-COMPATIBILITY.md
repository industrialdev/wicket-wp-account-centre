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

## Additive Defaults Already in the Library

The following areas are additive and safe by default because they ship disabled or empty:

- `membership.resolution.prefer_current_cycle`
- `access.permissions.role_only_management_access.*`
- `presentation.member_list.show_bulk_upload`
- `presentation.member_list.display_roles.allowlist`
- `presentation.member_list.display_roles.denylist`
- `presentation.member_list.remove_policy_callout.*`
- `membership.cycle.*`

## What Counts As Breaking

- changing default strategy behavior
- changing default permissions in a way that expands access
- removing a documented config key that exists in `OrgManConfig`
- removing the `get_instance()` bridge alias
- changing process-handler request shapes in a way that breaks existing templates
