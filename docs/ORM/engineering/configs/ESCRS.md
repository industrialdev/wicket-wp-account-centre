# ESCRS Configuration

Source of truth: `../escrs-website-wordpress/src/web/app/themes/wicket-child/custom/org-roster.php`

This document mirrors the current site override. If it drifts, update the site config first, then update this file.

## Active Strategy

- `membership.strategy = membership_cycle`

## Current Override Paths

### `membership`

- `membership.strategy = membership_cycle`
- `membership.cycle.permissions.add_member_roles = ['membership_owner', 'membership_manager']`
- `membership.cycle.permissions.remove_member_roles = ['membership_owner', 'membership_manager']`
- `membership.cycle.permissions.purchase_seat_roles = ['membership_owner']`
- `membership.cycle.prevent_owner_removal = true`

### `presentation`

- `presentation.organization_list.show_membership_details = true`
- `presentation.member_view.use_unified = true`
- `presentation.member_view.search_clear_requires_submit = true`

### `integrations.additional_seats`

- `integrations.additional_seats.enabled = true`
- `integrations.additional_seats.sku = additional-seats`
- `integrations.additional_seats.discount_sku = corporate-seat-discount`
- `integrations.additional_seats.form_slug = additional-seats`
- `integrations.additional_seats.min_quantity = 1`
- `integrations.additional_seats.max_quantity = 900`

### Multi-tier additional seats

ESCRS orgs may hold several active memberships (one per tier) at once. Each tier has its own WooCommerce seat product and the purchase callout passes the membership tier slug to Gravity Form 22 for conditional logic + correct product resolution. SKUs are derived as `{sku}-{tier-slug}` unless mapped explicitly.

- `integrations.additional_seats.tier_mode = true`
- `integrations.additional_seats.tier_slug_field = tier-slug`
- `integrations.additional_seats.tier_skus`:
  - `escrs-national-society` → `additional-seats-escrs-national-society`
  - `3-year-escrs-national-society` → `additional-seats-3-year-escrs-national-society`
  - `escrs-trainee-national-society` → `additional-seats-escrs-trainee-national-society`
  - `3-year-escrs-trainee-national-society` → `additional-seats-3-year-escrs-trainee-national-society`

## Active Filters

### `wicket/acc/orgman/membership_cycle_include_entry` — include delayed memberships

| | |
|---|---|
| Hook | `wicket/acc/orgman/membership_cycle_include_entry` |
| Priority / accepted args | `10 / 4` |
| Registered in | `after_setup_theme` (priority 20) |
| Source file | `src/web/app/themes/wicket-child/custom/org-roster.php` |
| Provided by | `WicketORM\` — `templates-partials/organization-list.php` |

**Problem.** By default the library only surfaces memberships where `active=true` or `in_grace=true`. Memberships purchased ahead of time carry both flags as `false` until `starts_at` is reached, so they are silently dropped from the organization list.

**Solution.** The child theme re-includes any membership whose `starts_at` is a non-empty ISO 8601 date that has not yet been reached (`strtotime($starts_at) > time()`). Already-included entries short-circuit immediately.

```php
add_filter('wicket/acc/orgman/membership_cycle_include_entry', static function (bool $include, array $attrs, array $data, string $org_uuid): bool {
    if ($include) {
        return true;
    }

    $starts_at = (string) ($attrs['starts_at'] ?? '');

    return $starts_at !== '' && strtotime($starts_at) > time();
}, 10, 4);
```

**Rendering.** A delayed entry renders with a grey **Inactive Membership** badge because `card-organization-membership-cycle.php` derives the active state from `active || in_grace`, both of which are `false` for delayed memberships. The **Manage Members** and **Edit Organization** action links still appear if the current user holds the required roles for that membership.

**Filter signature (from the library).**

```
apply_filters(
    'wicket/acc/orgman/membership_cycle_include_entry',
    bool   $include,   // true when active or in_grace — short-circuit if already true
    array  $attrs,     // membership attributes: active, in_grace, starts_at, ends_at, …
    array  $data,      // full membership entry: ['membership' => […], 'included' => […]]
    string $org_uuid   // UUID of the organization being evaluated
)
```

### `contacts`

- `contacts.enabled = true`

Activates the relationship-based contacts roster (President, President Elect, Secretary, CEO, Treasurer, Main Contact). Separate from the membership-based roster. Only `membership_manager` can view and manage contacts.

```php
add_filter('wicket/org-roster/config', static function (array $config): array {
    $config['membership']['strategy'] = 'membership_cycle';

    $config['membership']['cycle']['permissions']['add_member_roles'] = [
        'membership_owner',
        'membership_manager',
    ];
    $config['membership']['cycle']['permissions']['remove_member_roles'] = [
        'membership_owner',
        'membership_manager',
    ];
    $config['membership']['cycle']['permissions']['purchase_seat_roles'] = [
        'membership_owner',
    ];
    $config['membership']['cycle']['prevent_owner_removal'] = true;

    $config['contacts']['enabled'] = true;

    $config['presentation']['member_view']['use_unified'] = true;
    $config['presentation']['member_view']['search_clear_requires_submit'] = true;
    $config['presentation']['organization_list']['show_membership_details'] = true;
    $config['integrations']['additional_seats']['enabled'] = true;
    $config['integrations']['additional_seats']['sku'] = 'additional-seats';
    $config['integrations']['additional_seats']['discount_sku'] = 'corporate-seat-discount';
    $config['integrations']['additional_seats']['form_slug'] = 'additional-seats';

    // Multi-tier additional seats. ESCRS orgs may hold several active memberships (one per tier)
    // at once, so each tier has its own WooCommerce seat product and the purchase callout passes
    // the membership tier slug to Gravity Form 22 for conditional logic + correct product
    // resolution. SKUs are derived as '{sku}-{tier-slug}' unless mapped explicitly here.
    $config['integrations']['additional_seats']['tier_mode'] = true;
    $config['integrations']['additional_seats']['tier_slug_field'] = 'tier-slug';
    $config['integrations']['additional_seats']['tier_skus'] = [
        'escrs-national-society'               => 'additional-seats-escrs-national-society',
        '3-year-escrs-national-society'        => 'additional-seats-3-year-escrs-national-society',
        'escrs-trainee-national-society'      => 'additional-seats-escrs-trainee-national-society',
        '3-year-escrs-trainee-national-society'=> 'additional-seats-3-year-escrs-trainee-national-society',
    ];
    $config['integrations']['additional_seats']['min_quantity'] = 1;
    $config['integrations']['additional_seats']['max_quantity'] = 900;

    return $config;
});
```
