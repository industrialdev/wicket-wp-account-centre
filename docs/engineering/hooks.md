---
title: "Hooks"
audience: [developer, agent]
php_class: WicketAcc\WicketAcc
source_files: ["class-wicket-acc-main.php", "src/", "src/WicketORM/", "includes/"]
---

# Developer Hooks (Filters & Actions)

## Overview

WordPress filters and actions exposed by `wicket-wp-account-centre`, including the hooks fired by the in-tree `WicketORM\` org-roster library.

For the org-roster config filters specifically, see [config-filters.md](../ORM/engineering/config-filters.md).

---

## 1. General & Settings Filters

### `wicket/acc/should_enqueue_wicket_styles`
- **Type**: Filter
- **Description**: Determines if the default Wicket Account Centre CSS should be enqueued.
- **Default**: `true`
- **Usage**:
  ```php
  add_filter('wicket/acc/should_enqueue_wicket_styles', '__return_false');
  ```

### `wicket/acc/settings/wicket_theme`
- **Type**: Filter
- **Description**: Sets the default theme (`light` or `dark`) for the plugin.
- **Default**: `light`

### `wicket/acc/settings/wicket_prefer_color_scheme`
- **Type**: Filter
- **Description**: Overrides the CSS color scheme preference.

---

## 2. Navigation & Routing

### `wicket_acc_menu_items`
- **Type**: Filter
- **Description**: Modify the array of navigation menu items in the Account Centre.

### `wicket/acc/router/disable_router`
- **Type**: Filter
- **Description**: Completely disable the custom router logic.
- **Default**: `false`

### `wicket/acc/orgman/is_orgmanagement_page`
- **Type**: Filter
- **Description**: Determine if a specific post/page ID should be treated as an Organization Management page.

---

## 3. Block-Specific Hooks

### Welcome Block (`ac-welcome`)
- `wicket/acc/block/welcome_block_name`: Filter the name displayed in the greeting.
- `do_action('wicket/acc/block/after_welcome_block_name', $person_id)`: Triggered after the greeting name.
- `do_action('wicket/acc/block/before_welcome_block_memberships', $person_id)`: Triggered before the membership list.
- `wicket/acc/block/welcome_filter_memberships`: Filter the memberships displayed in the welcome block.
- `wicket/acc/block/welcome_non_member_text`: Filter the text shown for non-members.
- `wicket/acc/block/welcome/show_renewal_date`: Filter whether the renewal date row is shown.
- `wicket/acc/block/welcome/renewal_date_payload`: Override the renewal date label and/or timestamp.

### Callout Block (`ac-callout`)
- `wicket/acc/block/ac-callout/renewal_filter_product_data`: Filter membership categories for renewal logic.

---

## 4. Profile & User Hooks

### `wicket/acc/get_avatar`
- **Type**: Filter
- **Description**: Filter the URL for the user's profile avatar.

### `do_action('wicket/acc/profile/edit/profile_image_updated', $photo_url)`
- **Type**: Action
- **Description**: Backwards-compatible action triggered when a user updates or removes their profile picture.

### `do_action('wicket/acc/profile/edit/profile_image_deleted', $payload)`
- **Type**: Action
- **Description**: Fired on profile-picture removal with explicit delete payload for webhook consumers.
- **Payload**:
  - `event` (`deleted`)
  - `profile_image_url` (`null`)
  - `user_id`
  - `person_uuid`
  - `mdp_sync_success` (`bool`)
  - `timestamp` (unix epoch)

### `do_action('profile-image delete', $payload)`
- **Type**: Action
- **Description**: Lower-level profile-image delete action invoked by the explicit `clear_profile_image()` flow. Fires in addition to the backwards-compatible `wicket/acc/profile/edit/profile_image_deleted` action.

---

## 5. Organization Management

### `wicket/acc/shortcodes/org-selector/org-uuid-list`
- **Type**: Filter
- **Description**: Filter the list of organization UUIDs available in the organization selector shortcode.

### `wicket/acc/orgman/membership_cycle_include_entry`
- **Type**: Filter
- **Description**: Opt-in hook to include delayed memberships (those whose `starts_at` is in the future) in the org list when running `membership.strategy = membership_cycle`. By default only `active` and `in_grace` entries are included. ESCRS uses this to surface pre-purchased tiers.
- **Signature**:
  ```
  apply_filters(
      'wicket/acc/orgman/membership_cycle_include_entry',
      bool   $include,
      array  $attrs,
      array  $data,
      string $org_uuid
  )
  ```
- **Default**: returns `$include` unchanged.

---

## 6. WooCommerce Integration

### `wicket/acc/wc_endpoints`
- **Type**: Filter
- **Description**: Modify the list of WooCommerce endpoints managed by the Account Centre.
- **Usage**:
  ```php
  add_filter('wicket/acc/wc_endpoints', function($endpoints) {
      $endpoints['custom-endpoint'] = 'custom-slug';
      return $endpoints;
  });
  ```

---

## 7. Notifications

### `wicket/acc/notifications/approval_email_from`
- **Type**: Filter
- **Description**: Filter the "From" email address for approval notification emails.

---

## 8. OrgMan Config Filters

See [config-filters.md](../ORM/engineering/config-filters.md) for the full list. Highlights:

- `wicket/org-roster/config` — global config override (receives full array, returns full array).
- `wicket/acc/orgman/config` — legacy alias of the global filter.
- `wicket/org-roster/additional_seats_*` — per-field overrides for every additional-seats config value, including the tier-mode filters (`additional_seats_tier_mode`, `additional_seats_tier_skus`, `additional_seats_tier_slug_field`).
- `wicket/org-roster/allowed_document_types`, `wicket/org-roster/max_document_size`.
- `wicket_orgman_log_levels` — overrides the allowed log levels per environment.