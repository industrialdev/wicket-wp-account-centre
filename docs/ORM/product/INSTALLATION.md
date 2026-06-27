---
title: "Installation"
audience: [implementer, support]
php_class: WicketORM\OrgMan
source_files: ["src/WicketORM/OrgMan.php", "class-wicket-acc-main.php"]
---

# Installation

How `WicketORM\OrgMan` (org-roster) is installed and activated on a Wicket site.

`WicketORM\` lives inside and is booted by **`wicket-wp-account-centre`**.
Sites do not install, autoload, sync, or boot the library themselves.
A site's only responsibility is a small child-theme config override file.

## Requirements

- PHP 8.2+
- WordPress
- `wicket-wp-account-centre` plugin active (it provides `WicketORM\` and boots `OrgMan`)

Optional integrations:

- WooCommerce for additional seats
- Gravity Forms for the additional seats purchase form
- Wicket/MDP helper functions supplied by the host application

For product setup and operational behavior of the additional seats flow, see [ADDITIONAL-SEATS.md](ADDITIONAL-SEATS.md).

## How OrgMan Boots

`wicket-wp-account-centre` boots the orchestrator itself:

```php
// class-wicket-acc-main.php (abbreviated)
add_action(
    "after_setup_theme",
    static function (): void {
        if (class_exists(OrgMan::class)) {
            OrgMan::getInstance();
        }
    },
    20,
);
```

Consequences for site code:

- `OrgMan::getInstance()` runs on `after_setup_theme` priority **20**.
- `OrgMan` reads the `wicket/org-roster/config` filter during boot, so site overrides must be registered **before** that hook fires. A plain top-level `add_filter(...)` in the child theme's `custom/org-roster.php` (included from `functions.php`) satisfies this — theme files load before `after_setup_theme`.
- Sites must **not** call `OrgMan::get_instance()` / `getInstance()`, must **not** load any org-roster autoloader, and must **not** replicate the old library-root / `base_path` / `base_url` scaffold. account-centre owns all of that.

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

Working examples: per-site snapshots live in [`engineering/configs/`](../engineering/configs/) (e.g. [PACE](../engineering/configs/PACE.md), [CCHL](../engineering/configs/CCHL.md)).

## 2) Include the Override File From Theme `functions.php`

Add `org-roster.php` to the child theme's include list, for example:

```php
$wicket_child_includes = [
    'config-child.php',
    'acf.php',
    'org-roster.php',
];
```

## 3) Strategy and Behavior Overrides

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
- `supplemental-members`

## 6) Verification Checklist

1. `wicket-wp-account-centre` is active.
2. Child theme includes `custom/org-roster.php` and it contains an `add_filter('wicket/org-roster/config', ...)` callback.
3. No `OrgMan::get_instance()` / `getInstance()` call, no `require` of an org-roster autoloader, and no library-root scaffold in site code.
4. My Account CPT page slug exists: `organization-management`.
5. User has relevant memberships/roles in Wicket.
6. No fatal errors in PHP/WP logs.

## Common Failure Mode

If the page renders but the org list is empty with no `OrgMan` execution evidence, check that `wicket-wp-account-centre` is active and that the child-theme override file is included and registering its `wicket/org-roster/config` callback before `after_setup_theme` priority 20.
