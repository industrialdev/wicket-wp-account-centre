---
title: "Membership Cycle Strategy: Config Schema"
audience: [developer, implementer]
slug: membership-cycle-config-schema
source_files:
  - "src/Config/OrgManConfig.php"
---

# Membership Cycle Strategy: Config Schema

Membership-cycle mode uses the `membership.cycle` namespace plus shared UI config and shared access config.

## Strategy And Scope

- `membership.strategy = membership_cycle`
- `membership.cycle.key` — internal strategy identifier (default `membership_cycle`)

## Cycle Permissions

- `membership.cycle.permissions.add_member_roles` — default `['membership_manager']`
- `membership.cycle.permissions.remove_member_roles` — default `['membership_manager']`
- `membership.cycle.permissions.purchase_seat_roles` — default `['membership_owner', 'membership_manager', 'org_editor']`

## Cycle Removal Behavior

- `membership.cycle.prevent_owner_removal` — default `true`. Owner removal blocked by default for cycle mode.
- The shared `access.permissions.owner_removal_requires_membership_owner_role` flag applies on top: when `true`, only a user who also holds `membership_owner` can remove another `membership_owner`.

## Resolution And Filtering

- `membership.resolution.prefer_current_cycle` — when `true`, prefer the manager's currently-active cycle when resolving which membership UUID to mutate.
- `wicket/acc/orgman/membership_cycle_include_entry` filter — opt-in hook for sites that need to include delayed (`starts_at` in the future) memberships in the org list. ESCRS uses this to render pre-purchased memberships with an "Inactive Membership" badge.

## Multi-Tier Additional Seats

- `integrations.additional_seats.tier_mode = true` — opt-in flag.
- `integrations.additional_seats.tier_skus` — map of `tier-slug => sku`.
- `integrations.additional_seats.tier_slug_field` — GF field name carrying the tier slug (default `tier-slug`).

## Shared UI Config

- `presentation.member_list.show_bulk_upload`
- `presentation.member_list.use_legacy_list`
- `presentation.member_view.use_unified`
- `presentation.organization_list.show_membership_details`

## Contacts

The contacts roster (`contacts.*`) is strategy-agnostic and can be enabled alongside membership_cycle mode.