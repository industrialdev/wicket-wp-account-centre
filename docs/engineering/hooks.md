---
title: "Hooks"
audience: [developer, agent]
php_class: Wicket_ACC_Main
source_files: ["src/"]
---

# Developer Hooks (Filters & Actions)

## Overview
The Wicket Account Centre plugin provides several WordPress filters and actions to allow developers to customize behavior, modify data, and hook into plugin processes.

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
- **Usage**:
  ```php
  add_filter('wicket_acc_menu_items', function($items) {
      $items['custom-link'] = [
          'title' => __('Custom Link', 'text-domain'),
          'url' => home_url('/custom-path/'),
      ];
      return $items;
  });
  ```

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

### Callout Block (`ac-callout`)
- `wicket/acc/block/ac-callout/renewal_filter_product_data`: Filter membership categories for renewal logic.

---

## 4. Profile & User Hooks

### `wicket/acc/get_avatar`
- **Type**: Filter
- **Description**: Filter the URL for the user's profile avatar.

### `do_action('wicket/acc/profile/edit/profile_image_updated', $photo_url)`
- **Type**: Action
- **Description**: Triggered when a user successfully updates or removes their profile picture.

---

## 5. Organization Management

### `wicket/acc/shortcodes/org-selector/org-uuid-list`
- **Type**: Filter
- **Description**: Filter the list of organization UUIDs available in the organization selector shortcode.

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
- **Description**: Filter the "From" email address for approval notification emails."
