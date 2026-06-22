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

## Current Config Function

```php
function wicket_orgman_config(array $config): array
{
    $config['membership']['strategy'] = 'cascade';

    // MDP cascade membership is configured to trigger on 'employee' relationship type only.
    // The library default is 'Position' — override it here so the cascade fires correctly.
    $config['relationships']['defaults']['type'] = 'employee';
    $config['integrations']['additional_seats']['enabled'] = true;
    $config['integrations']['additional_seats']['sku'] = 'additional-seats';
    $config['integrations']['additional_seats']['discount_sku'] = 'corporate-seat-discount';
    $config['integrations']['additional_seats']['form_id'] = 0;
    $config['integrations']['additional_seats']['form_slug'] = 'additional-seats';
    $config['integrations']['additional_seats']['min_quantity'] = 1;
    $config['integrations']['additional_seats']['max_quantity'] = 900;

    return $config;
}
```
