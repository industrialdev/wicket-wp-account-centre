---
title: "Installation"
audience: [implementer, support]
php_class: WicketORM\OrgMan
source_files: ["src/OrgMan.php"]
---

# Installation

This guide shows the supported way to load `industrialdev/wicket-lib-org-roster` in WordPress sites.

Supported install modes:
- Standard WordPress (root Composer install).
- Bedrock (root Composer install + sync to `web/app/libs/wicket-lib-org-roster`).

## Requirements

- PHP 8.2+
- WordPress
- Composer autoload available before `OrgMan.php` runs

Optional integrations:

- WooCommerce for additional seats
- Gravity Forms for the additional seats purchase form
- Wicket/MDP helper functions supplied by the host application

For product setup and operational behavior of the additional seats flow, see [ADDITIONAL-SEATS.md](ADDITIONAL-SEATS.md).

## Why `after_setup_theme`

Initialize OrgMan on `after_setup_theme` (priority `20`), not `plugins_loaded`.

`after_setup_theme` is the reliable point where theme code, Composer autoloading, and my-account template behavior are consistently available for this library.

## 1) Install With Composer

From your site root (directory containing WordPress and root `composer.json`), add this repository entry:

```json
{
  "type": "vcs",
  "url": "git@github.com:industrialdev/wicket-lib-org-roster.git"
}
```

Required root `composer.json` baseline:

```json
{
  "minimum-stability": "RC",
  "prefer-stable": true,
  "require": {
    "industrialdev/wicket-lib-org-roster": "^0@dev"
  }
}
```

Why `@dev` stability is required:
- `industrialdev/wicket-lib-org-roster` depends on `starfederation/datastar-php:^1@dev`.
- Keep `"industrialdev/wicket-lib-org-roster": "^0@dev"` while that dependency chain includes pre-stable Datastar releases.

Once `datastar-php` reaches a stable RC, you can tighten the constraint:

```json
{
  "require": {
    "industrialdev/wicket-lib-org-roster": "^0@RC"
  }
}
```

Note: the repository entry optionally accepts a `"name"` key for clarity:

```json
{
  "repositories": [
    {
      "name": "wicket-lib-org-roster",
      "type": "vcs",
      "url": "git@github.com:industrialdev/wicket-lib-org-roster.git"
    }
  ]
}
```

## 1.1) Sync Library Into Public `libs`

Assets and templates must be publicly accessible. The sync script copies the library from `vendor/` into a public path after install/update. This is required for all install types.

- **Standard WordPress** → syncs to `wp-content/libs/wicket-lib-org-roster`
- **Bedrock** → syncs to `web/app/libs/wicket-lib-org-roster`

Add the sync script to your `composer.json` scripts:

```json
{
  "scripts": {
    "orgman:sync-lib": [
      "@php vendor/industrialdev/wicket-lib-org-roster/.ci/sync-orgman-lib.php"
    ],
    "post-install-cmd": [
      "@orgman:sync-lib"
    ],
    "post-update-cmd": [
      "@orgman:sync-lib"
    ]
  }
}
```

The script auto-detects the layout and writes to the correct public path.

Full Bedrock `composer.json` example (minimal, showing all required OrgMan keys):

```json
{
  "name": "roots/bedrock",
  "type": "project",
  "minimum-stability": "RC",
  "prefer-stable": true,
  "repositories": [
    {
      "name": "wicket-lib-org-roster",
      "type": "vcs",
      "url": "git@github.com:industrialdev/wicket-lib-org-roster.git"
    }
  ],
  "require": {
    "industrialdev/wicket-lib-org-roster": "^0@RC"
  },
  "extra": {
    "installer-paths": {
      "web/app/mu-plugins/{$name}/": ["type:wordpress-muplugin"],
      "web/app/plugins/{$name}/":    ["type:wordpress-plugin"],
      "web/app/themes/{$name}/":     ["type:wordpress-theme"]
    },
    "wordpress-install-dir": "web/wp"
  },
  "scripts": {
    "orgman:sync-lib": [
      "@php vendor/industrialdev/wicket-lib-org-roster/.ci/sync-orgman-lib.php"
    ],
    "post-install-cmd": [
      "@orgman:sync-lib"
    ],
    "post-update-cmd": [
      "@orgman:sync-lib"
    ]
  }
}
```

Standard WordPress `composer.json` example:

```json
{
  "minimum-stability": "RC",
  "prefer-stable": true,
  "repositories": [
    {
      "name": "wicket-lib-org-roster",
      "type": "vcs",
      "url": "git@github.com:industrialdev/wicket-lib-org-roster.git"
    }
  ],
  "require": {
    "industrialdev/wicket-lib-org-roster": "^0@RC"
  },
  "scripts": {
    "orgman:sync-lib": [
      "@php vendor/industrialdev/wicket-lib-org-roster/.ci/sync-orgman-lib.php"
    ],
    "post-install-cmd": [
      "@orgman:sync-lib"
    ],
    "post-update-cmd": [
      "@orgman:sync-lib"
    ]
  }
}
```

## 2) Bootstrap File (`custom/org-roster.php`)

Use a layout-aware bootstrap that works for both Standard WordPress and Bedrock.

```php
<?php

use WicketORM\OrgMan;

defined('ABSPATH') || exit;

function wicket_orgman_is_valid_library_root(string $candidate): bool
{
    if (!is_dir($candidate) || !is_readable($candidate)) {
        return false;
    }

    return is_file(trailingslashit($candidate) . 'src/OrgMan.php');
}

function wicket_orgman_is_path_within(string $path, string $root): bool
{
    $normalized_path = untrailingslashit(str_replace('\\', '/', $path));
    $normalized_root = untrailingslashit(str_replace('\\', '/', $root));

    if ($normalized_path === '' || $normalized_root === '') {
        return false;
    }

    return $normalized_path === $normalized_root || strpos($normalized_path, $normalized_root . '/') === 0;
}

function wicket_orgman_library_root(): ?string
{
    static $resolved = null;
    static $ready    = false;

    if ($ready) {
        return $resolved;
    }
    $ready = true;

    $bedrock_root = dirname(untrailingslashit(ABSPATH));

    $candidates = [
        trailingslashit(WP_CONTENT_DIR) . 'libs/wicket-lib-org-roster',
        trailingslashit(ABSPATH) . 'app/libs/wicket-lib-org-roster',
        trailingslashit(WP_CONTENT_DIR) . 'vendor/industrialdev/wicket-lib-org-roster',
        trailingslashit(ABSPATH) . 'vendor/industrialdev/wicket-lib-org-roster',
        trailingslashit($bedrock_root) . 'vendor/industrialdev/wicket-lib-org-roster',
    ];

    foreach ($candidates as $candidate) {
        if (wicket_orgman_is_valid_library_root($candidate)) {
            $resolved = untrailingslashit($candidate);
            break;
        }
    }

    return $resolved;
}

function wicket_orgman_public_library_root(): ?string
{
    $library_root = wicket_orgman_library_root();
    if (empty($library_root)) {
        return null;
    }

    // Already under a public path — use it directly.
    if (wicket_orgman_is_path_within($library_root, WP_CONTENT_DIR)) {
        return $library_root;
    }

    if (wicket_orgman_is_path_within($library_root, ABSPATH)) {
        return $library_root;
    }

    // Library is in a non-public vendor dir; fall back to synced public copies.
    $public_candidates = [
        trailingslashit(WP_CONTENT_DIR) . 'libs/wicket-lib-org-roster',
        trailingslashit(ABSPATH) . 'app/libs/wicket-lib-org-roster',
    ];

    foreach ($public_candidates as $candidate) {
        if (wicket_orgman_is_valid_library_root($candidate)) {
            return untrailingslashit($candidate);
        }
    }

    return null;
}

function wicket_orgman_load_autoloader(): void
{
    if (class_exists(OrgMan::class, false)) {
        return;
    }

    $bedrock_root      = dirname(untrailingslashit(ABSPATH));
    $autoload_candidates = [];

    $library_root = wicket_orgman_library_root();
    if (!empty($library_root)) {
        $autoload_candidates[] = trailingslashit($library_root) . 'vendor/autoload.php';
    }

    $autoload_candidates[] = trailingslashit(WP_CONTENT_DIR) . 'vendor/autoload.php';
    $autoload_candidates[] = trailingslashit(ABSPATH) . 'vendor/autoload.php';
    $autoload_candidates[] = trailingslashit($bedrock_root) . 'vendor/autoload.php';

    foreach ($autoload_candidates as $autoload_file) {
        if (is_readable($autoload_file)) {
            require_once $autoload_file;
            break;
        }
    }
}

function wicket_orgman_config(array $config): array
{
    // mutate config...
    return $config;
}

function wicket_orgman_base_path(string $default): string
{
    $library_root = wicket_orgman_library_root();

    return !empty($library_root) ? $library_root : $default;
}

function wicket_orgman_base_url(string $default): string
{
    $library_root = wicket_orgman_public_library_root();
    if (empty($library_root)) {
        return $default;
    }

    if (wicket_orgman_is_path_within($library_root, WP_CONTENT_DIR)) {
        $relative_path = ltrim(substr($library_root, strlen(untrailingslashit(WP_CONTENT_DIR))), '/');

        return trailingslashit(content_url($relative_path));
    }

    if (wicket_orgman_is_path_within($library_root, ABSPATH)) {
        $relative_path = ltrim(substr($library_root, strlen(untrailingslashit(ABSPATH))), '/');

        return trailingslashit(site_url($relative_path));
    }

    return $default;
}

add_action('after_setup_theme', static function (): void {
    static $booted = false;
    if ($booted) {
        return;
    }
    $booted = true;

    wicket_orgman_load_autoloader();

    if (!has_filter('wicket/org-roster/config', 'wicket_orgman_config')) {
        add_filter('wicket/org-roster/config', 'wicket_orgman_config');
    }
    if (!has_filter('wicket/org-roster/base_path', 'wicket_orgman_base_path')) {
        add_filter('wicket/org-roster/base_path', 'wicket_orgman_base_path');
    }
    if (!has_filter('wicket/org-roster/base_url', 'wicket_orgman_base_url')) {
        add_filter('wicket/org-roster/base_url', 'wicket_orgman_base_url');
    }

    if (class_exists(OrgMan::class)) {
        OrgMan::get_instance();
    }
}, 20);
```

Important: register `wicket/org-roster/config` before `OrgMan::get_instance()` so initial service/config boot uses your overrides.

## 3) Theme Styling Overrides (Recommended)

OrgMan ships its own base stylesheet (`orgman-modern`).
To customize the UI safely from your theme, enqueue a second stylesheet that depends on `orgman-modern`.

Recommended child-theme path:
- `assets/css/org-roster.css`

Starter demo file (inside this library):
- `public/css/org-roster-theme-overrides.demo.css`

Example copy commands:

Standard WordPress:
```bash
cp vendor/industrialdev/wicket-lib-org-roster/public/css/org-roster-theme-overrides.demo.css wp-content/themes/your-theme/assets/css/org-roster.css
```

Bedrock (after sync to `web/app/libs`):
```bash
cp web/app/libs/wicket-lib-org-roster/public/css/org-roster-theme-overrides.demo.css web/app/themes/your-theme/assets/css/org-roster.css
```

Add this to `custom/org-roster.php`:

```php
<?php

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

## 4) Include Bootstrap File From Theme `functions.php`

Example include list:

```php
$wicket_child_includes = [
    'config-child.php',
    'acf.php',
    'org-roster.php',
];
```

## 5) Strategy/Behavior Configuration (Optional)

Inside the bootstrap filter, set strategy and client-specific config:

```php
add_filter('wicket/org-roster/config', static function (array $config): array {
    $config['membership']['strategy'] = 'membership_cycle';
    $config['presentation']['member_list']['show_bulk_upload'] = true; // default false

    return $config;
});
```

## 6) Required WordPress Pages

The library expects account-page slugs matching:

- `organization-management`
- `organization-profile`
- `organization-members`
- `organization-members-bulk`
- `supplemental-members`

## 7) Path and URL Resolution

The library auto-resolves asset paths for common layouts, including:

- `wp-content/libs/...`
- `web/app/libs/...`
- root `vendor/...`

You can override this with:

- `wicket/org-roster/base_path`
- `wicket/org-roster/base_url`

## 8) Verification Checklist

1. Library exists in at least one supported location:
   - Standard WP: `vendor/industrialdev/wicket-lib-org-roster`
   - Bedrock public copy: `web/app/libs/wicket-lib-org-roster`
2. Composer autoloader is loaded from one of the bootstrap candidates.
3. `OrgMan::get_instance()` runs on `after_setup_theme`.
4. My Account CPT page slug exists: `organization-management`.
5. User has relevant memberships/roles in Wicket.
6. No fatal errors in PHP/WP logs.

Bedrock-specific checks:
- `composer run-script orgman:sync-lib` succeeds.
- Assets resolve from `/app/libs/wicket-lib-org-roster/public/...`.

## Common Failure Mode

If the page renders but org list is empty with no OrgMan execution evidence, verify the bootstrap hook first. Using `plugins_loaded` can prevent expected initialization timing in some theme setups.
