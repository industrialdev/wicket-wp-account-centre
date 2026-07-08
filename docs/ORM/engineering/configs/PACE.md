---
title: "PACE Configuration"
audience: [implementer, support, developer]
source_files: ["../pace-website-wordpress/src/web/app/themes/wicket-child/custom/org-roster.php"]
---

# PACE Configuration

Source of truth: `../pace-website-wordpress/src/web/app/themes/wicket-child/custom/org-roster.php`

This document mirrors the current site override. If it drifts, update the site config first, then update this file.

## Active Strategy

- `membership.strategy = cascade`

## Notes

PACE's MDP membership has "Cascading Membership Settings" enabled with cascade type "Cascade to organization's relationships only" and allowed resource "Employee". This means MDP will only auto-grant a membership to a person when they are connected to the org with relationship type `employee`. The library default is `Position`, so the relationship type must be overridden here or the MDP cascade will never fire.

## Current Override Paths

### `membership`

- `membership.strategy = cascade`

### `relationships`

- `relationships.defaults.type = employee`


### `integrations.additional_seats`

- `integrations.additional_seats.enabled = true`
- `integrations.additional_seats.sku = additional-seats`
- `integrations.additional_seats.discount_sku = corporate-seat-discount`
- `integrations.additional_seats.form_id = 0`
- `integrations.additional_seats.form_slug = additional-seats`
- `integrations.additional_seats.min_quantity = 1`
- `integrations.additional_seats.max_quantity = 900`

## Current Override File

`OrgMan` is booted by `wicket-wp-account-centre` at `after_setup_theme` priority 20. This file registers **only** PACE's config overrides — it does not boot `OrgMan` and does not load any autoloader. See [Setup](../../product/SETUP.md).

```php
<?php
/**
 * Site-specific WicketORM\ org-roster config.
 *
 * OrgMan is now booted by wicket-wp-account-centre (at after_setup_theme
 * priority 20). This file only registers THIS SITE's config overrides,
 * which account-centre reads when it boots OrgMan. Do not boot OrgMan here
 * and do not load any org-roster autoloader. account-centre handles both.
 */

defined('ABSPATH') || exit;

add_filter('wicket/org-roster/config', static function (array $config): array {
    // PACE: MDP cascade membership triggers on 'employee' relationship type only.
    // The library default is 'Position', so override it here so the cascade fires.
    $config['membership']['strategy'] = 'cascade';
    $config['relationships']['defaults']['type'] = 'employee';

    // PACE: additional seats integration.
    $config['integrations']['additional_seats']['enabled'] = true;
    $config['integrations']['additional_seats']['sku'] = 'additional-seats';
    $config['integrations']['additional_seats']['discount_sku'] = 'corporate-seat-discount';
    $config['integrations']['additional_seats']['form_id'] = 0;
    $config['integrations']['additional_seats']['form_slug'] = 'additional-seats';
    $config['integrations']['additional_seats']['min_quantity'] = 1;
    $config['integrations']['additional_seats']['max_quantity'] = 900;

    return $config;
});
```
