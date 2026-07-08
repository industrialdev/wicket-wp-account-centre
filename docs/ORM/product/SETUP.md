---
title: "Org Roster Setup"
audience: [implementer, support]
php_class: WicketORM\OrgMan
source_files: ["src/OrgMan.php", "src/Config/OrgManConfig.php", "class-wicket-acc-main.php"]
---

# Org Roster Setup

How a site activates and configures the org-roster feature (`WicketORM\`) once `wicket-wp-account-centre` is installed and active.

`WicketORM\` is **booted automatically** by `wicket-wp-account-centre` on `after_setup_theme` priority 20. There is no separate install, autoload, or bootstrap step for the site to perform.

What a site *does* need to do:

1. Create a child-theme config override file (only if the site needs non-default behavior).
2. Include that file from `functions.php`.
3. Make sure the required WordPress page slugs exist.

## When Do You Need This

You need a child-theme override file when the site wants to:

- Switch strategy (e.g., `membership.strategy = membership_cycle` instead of `direct`).
- Enable the additional-seats flow (`integrations.additional_seats.enabled = true`).
- Enable the contacts roster (`contacts.enabled = true`).
- Enable async CSV exports (`exports.enabled = true`).
- Enable MDP engagement/donation display (`engagement.enabled = true`).
- Tighten or relax owner-removal rules.
- Override any other config key documented in [CONFIGURATION.md](CONFIGURATION.md).

If the site is fine with every default, no override file is needed.

## 1) Create the Config Override File

In the child theme, create `custom/org-roster.php`. This file registers **only** this site's overrides against the `wicket/org-roster/config` filter.

Minimal skeleton:

```php
<?php
/**
 * Site-specific WicketORM\ org-roster config.
 *
 * OrgMan is booted by wicket-wp-account-centre (at after_setup_theme
 * priority 20). This file only registers THIS SITE's config overrides,
 * which account-centre reads when it boots OrgMan. Do not boot OrgMan here
 * and do not load any org-roster autoloader. account-centre handles both.
 */

defined('ABSPATH') || exit;

add_filter('wicket/org-roster/config', static function (array $config): array {
    // Site overrides go here. Example:
    // $config['membership']['strategy'] = 'cascade';

    return $config;
});
```

Working examples for active sites live under [`engineering/configs/`](../engineering/configs/):

- [PACE](../engineering/configs/PACE.md) — `cascade` strategy
- [CCHL](../engineering/configs/CCHL.md) — `direct` strategy
- [ESCRS](../engineering/configs/ESCRS.md) — `membership_cycle` strategy with multi-tier additional seats
- [IAA](../engineering/configs/IAA.md) — `groups` strategy
- [MSA](../engineering/configs/MSA.md) — `cascade` strategy with `membership.seat_limits.tier_max_assignments`
- [NJBIA](../engineering/configs/NJBIA.md) — `cascade` strategy with `member_contact` defaults
- [CSAE](../engineering/configs/CSAE.md) — `direct` strategy with additional seats off
- [Exports & Engagement Example](../engineering/configs/EXPORTS-ENGAGEMENT-EXAMPLE.md) — opt-in `exports` and `engagement` config

## 2) Include the Override File From Theme `functions.php`

Add `org-roster.php` to the child theme's include list, for example:

```php
$wicket_child_includes = [
    'config-child.php',
    'acf.php',
    'org-roster.php',
];
```

## 3) Strategy And Behavior Overrides

Set inside the config filter callback. Common keys:

```php
add_filter('wicket/org-roster/config', static function (array $config): array {
    $config['membership']['strategy'] = 'membership_cycle'; // direct|cascade|groups|membership_cycle
    $config['presentation']['member_list']['show_bulk_upload'] = true; // default false

    return $config;
});
```

For the full schema see [CONFIGURATION.md](CONFIGURATION.md). For per-field filter hooks (additional seats, documents, etc.) see [config-filters.md](../engineering/config-filters.md).

## 4) Theme Styling Overrides (Optional)

`OrgMan` ships its own base stylesheet (`orgman-modern`). To customize safely from the theme, enqueue a second stylesheet that depends on `orgman-modern`.

Recommended child-theme path: `assets/css/org-roster.css`.

```php
add_action('wp_enqueue_scripts', static function (): void {
    if (!wp_style_is('orgman-modern', 'enqueued') && !wp_style_is('orgman-modern', 'registered')) {
        return;
    }

    $relative_path = 'assets/css/org-roster.css';
    $file_path = trailingslashit(get_stylesheet_directory()) . $relative_path;
    if (!file_exists($file_path)) {
        return;
    }

    wp_enqueue_style(
        'wicket-child-org-roster',
        trailingslashit(get_stylesheet_directory_uri()) . $relative_path,
        ['orgman-modern'],
        (string) filemtime($file_path)
    );
}, 30);
```

## 5) Required WordPress Pages

The library expects account-page slugs matching:

- `organization-management`
- `organization-profile`
- `organization-members`
- `organization-members-bulk`
- `organization-contacts` (only when `contacts.enabled = true`)
- `supplemental-members`

Legacy ACC compatibility slugs (`org-management`, `org-management-profile`, `org-management-members`, `org-management-roster`) route to the same templates and can be used as drop-in aliases during migration.

## 6) Verification Checklist

1. `wicket-wp-account-centre` is active.
2. Child theme includes `custom/org-roster.php` and it contains an `add_filter('wicket/org-roster/config', ...)` callback.
3. No `OrgMan::get_instance()` / `getInstance()` call, no `require` of an org-roster autoloader, and no library-root scaffold in site code.
4. My Account CPT page slug exists: `organization-management`. If `contacts.enabled = true`, also confirm `organization-contacts`.
5. User has relevant memberships/roles in Wicket.
6. No fatal errors in PHP/WP logs.

## Common Failure Mode

If the page renders but the org list is empty with no `OrgMan` execution evidence, check that `wicket-wp-account-centre` is active and that the child-theme override file is included and registering its `wicket/org-roster/config` callback before `after_setup_theme` priority 20.

## Notes

- `OrgMan` reads the `wicket/org-roster/config` filter during boot. Theme overrides must be registered before that hook fires. A plain top-level `add_filter(...)` in the child theme's `custom/org-roster.php` (included from `functions.php`) satisfies this because theme files load before `after_setup_theme`.
- A defer-guard yields `OrgMan::getInstance()` to the standalone WicketORM plugin if both happen to be active at the same time. Sites mid-migration can leave the standalone plugin in place until they're ready to remove it; once it is gone, account-centre takes over automatically.