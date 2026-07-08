---
title: "Additional Seats"
audience: [implementer, support]
config_path: integrations.additional_seats
php_class: WicketORM\Services\AdditionalSeatsService
critical_doc_contract: true
critical_section_start: "<!-- CRITICAL:ADDITIONAL_SEATS_START -->"
critical_section_end: "<!-- CRITICAL:ADDITIONAL_SEATS_END -->"
required_items:
  fail_closed_purchase_context: "Purchase form URL generation fails closed when purchase context user-meta cannot be persisted."
  admin_setup_warning: "WordPress administrators see an in-page setup warning when additional-seats prerequisites are incomplete (missing purchasable SKU product, form mapping, or `supplemental-members` page)."
  tier_mode_warning: "When `tier_mode = true` but no `tier_skus` are configured, admins see a setup warning that surfaces the expected form slug and the tier-slug field parameter."
---

# Additional Seats

The additional-seats flow lets organization managers purchase extra member slots through a Gravity Forms + WooCommerce handoff, without leaving the account area. The flow supports two modes:

- **Single-SKU** (default): one WooCommerce product per org, regardless of how many tiers the org holds.
- **Multi-tier** (opt-in): one WooCommerce product per membership tier, for orgs that hold several active tiers at once (e.g. ESCRS).

Single-SKU sites leave the new tier keys unset and behave exactly as before.

## How It Works (Single-SKU Mode)

1. A manager on the `supplemental-members` page clicks "Purchase Additional Seats".
2. The library stores the current membership ID and organization UUID in user meta and session.
3. The user is redirected to a Gravity Forms form (configured via `integrations.additional_seats.form_id` or `form_slug`).
4. On form submission, WooCommerce adds the additional-seats product to the cart and redirects to checkout.
5. On order completion, `OrgMan` processes the order and updates the seat count for the organization membership.

**Fail-closed guarantee:** if the purchase context (membership ID + org UUID) cannot be persisted to user meta before the redirect, the URL is not generated and the flow does not proceed.

<!-- CRITICAL:ADDITIONAL_SEATS_START -->
Purchase form URL generation fails closed when purchase context user-meta cannot be persisted.
WordPress administrators see an in-page setup warning when additional-seats prerequisites are incomplete (missing purchasable SKU product, form mapping, or `supplemental-members` page).
When `tier_mode = true` but no `tier_skus` are configured, admins see a setup warning that surfaces the expected form slug and the tier-slug field parameter.
<!-- CRITICAL:ADDITIONAL_SEATS_END -->

## How It Works (Multi-Tier Mode)

Multi-tier mode is for orgs that hold several active membership tiers at once. Each tier has its own WooCommerce seat product and the purchase callout passes the membership tier slug to Gravity Forms. GF uses that value for conditional logic and to pick the correct tier product on submission.

1. Manager on `supplemental-members` page clicks "Purchase Additional Seats".
2. The purchase callout carries the membership tier slug through to Gravity Forms (via the `integrations.additional_seats.tier_slug_field` parameter).
3. Gravity Forms drives conditional logic with the tier slug and submits with the matching tier product.
4. WooCommerce adds the tier-specific product to the cart and redirects to checkout.
5. On order completion, `AdditionalSeatsService` reads the tier slug from the cart item, classifies the line item to a tier, and bumps `organization_memberships.max_assignments` for **that** tier's membership record.

Multi-tier fulfilment is **per line item** and **MDP-only**:

- Each tier seat line item bumps the matching `organization_memberships.max_assignments` by the line-item quantity.
- Idempotency per line item is tracked via the `tier_seats_applied` order-meta flag. Re-running the fulfilment hook on the same order will not double-bump the seat count.
- Partial-fulfilment retries use the `tier_seats_partial` order-meta flag so re-processing an already-fulfilled order is safe.
- Truth comes from the cart item data, not user meta — a single user-meta record cannot represent several memberships, so the cart item is the source of record for tier classification.

If `tier_mode = true` is set without a `tier_skus` map, the runtime warns the admin and the flow runs in a degraded state where SKUs are derived as `{sku}-{tier-slug}` at lookup time.

## Requirements

- WooCommerce active and configured
- Gravity Forms active
- A WooCommerce product with SKU matching `integrations.additional_seats.sku` (default: `additional-seats`)
- A Gravity Forms form identified by `integrations.additional_seats.form_id` or `integrations.additional_seats.form_slug`
- The `supplemental-members` WordPress page slug must exist

Multi-tier mode additionally requires:

- `integrations.additional_seats.tier_mode = true`
- A `tier_skus` map (recommended) or default SKU derivation
- A Gravity Forms form that reads the tier-slug parameter (`tier_slug_field`, default `tier-slug`)

## Enabling Single-SKU Flow

```php
add_filter('wicket/org-roster/config', static function (array $config): array {
    $config['integrations']['additional_seats']['enabled']  = true;
    $config['integrations']['additional_seats']['sku']      = 'additional-seats';
    $config['integrations']['additional_seats']['form_id']  = 42; // your GF form ID, or 0
    $config['integrations']['additional_seats']['form_slug'] = 'additional-seats';

    return $config;
});
```

## Enabling Multi-Tier Flow

```php
add_filter('wicket/org-roster/config', static function (array $config): array {
    $config['integrations']['additional_seats']['enabled']       = true;
    $config['integrations']['additional_seats']['sku']           = 'additional-seats';
    $config['integrations']['additional_seats']['discount_sku']  = 'corporate-seat-discount';
    $config['integrations']['additional_seats']['form_slug']     = 'additional-seats';

    // Multi-tier opt-in.
    $config['integrations']['additional_seats']['tier_mode']       = true;
    $config['integrations']['additional_seats']['tier_slug_field'] = 'tier-slug';
    $config['integrations']['additional_seats']['tier_skus']       = [
        'escrs-national-society'         => 'additional-seats-escrs-national-society',
        '3-year-escrs-national-society'  => 'additional-seats-3-year-escrs-national-society',
        'escrs-trainee-national-society' => 'additional-seats-escrs-trainee-national-society',
    ];

    return $config;
});
```

When `tier_skus` is non-empty the library resolves a product per tier from this map. When `tier_mode = true` but `tier_skus` is empty, SKUs are derived as `{sku}-{tier-slug}` at runtime and admins see a setup warning on `supplemental-members`.

## Config Reference

All keys live under `integrations.additional_seats`. See [CONFIGURATION.md](CONFIGURATION.md) for the full schema.

| Config key | Default | Description |
|---|---|---|
| `enabled` | `false` | Master switch. Must be `true` for the flow to activate. |
| `sku` | `additional-seats` | WooCommerce product SKU for the seat purchase product (single-SKU mode). |
| `discount_sku` | `corporate-seat-discount` | SKU for the optional seat discount product. |
| `form_id` | `0` | Gravity Forms form ID. `0` means "resolve by slug". |
| `form_slug` | `additional-seats` | Gravity Forms form slug used in URL generation and form-by-slug resolution. |
| `min_quantity` | `1` | Minimum number of seats purchasable in one order. |
| `max_quantity` | `900` | Maximum number of seats purchasable in one order. Legacy single-SKU mode hard-caps at this value. Multi-tier mode honours the configured per-tier `max_quantity` field on the form instead. |
| `tier_mode` | `false` | Opt-in flag for multi-tier flow. |
| `tier_skus` | `[]` | Map of `tier-slug => sku` for multi-tier mode. Empty falls back to `{sku}-{tier-slug}` derivation. |
| `tier_slug_field` | `tier-slug` | GF field name from which the tier slug is read at submit time. |

## Admin Setup Warning

When `integrations.additional_seats.enabled` is `true` and any of the following are missing, WordPress administrators see an in-page warning on the `supplemental-members` page:

- No WooCommerce product with the configured SKU (or any tier SKU in multi-tier mode)
- `form_id` is `0` and `form_slug` resolves to no form
- The `supplemental-members` page does not exist
- `tier_mode = true` but `tier_skus` is empty (warning also surfaces the expected form slug and the tier-slug field parameter so the implementer can finish the wiring)

Non-admin users do not see the warning; they see the page as if additional seats were unavailable.

## Troubleshooting

**The "Purchase Additional Seats" button does not appear.**
- Confirm `integrations.additional_seats.enabled` is `true`.
- Confirm the current user has a role listed in `access.permissions.purchase_seat_roles` (default: `membership_owner`, `membership_manager`, `org_editor`).
- Confirm the `supplemental-members` page slug exists.

**The form redirects but the cart is empty.**
- Verify the WooCommerce product SKU matches `integrations.additional_seats.sku` (single-SKU mode) or the relevant `tier_skus[slug]` (multi-tier mode).
- In multi-tier mode, check that the tier slug from the form matches a key in `tier_skus` (or that SKU derivation `{sku}-{tier-slug}` resolves to a real product).
- Check WooCommerce logs for product lookup failures.

**Seat count does not update after order completion.**
- Confirm the order processed successfully in WooCommerce (check order status).
- Check PHP error logs for exceptions from `AdditionalSeatsService`.
- Verify membership ID and org UUID are stored in the order's item meta (`membership_id`, `org_uuid`).
- In multi-tier mode, verify each tier line item bumped the correct tier's `max_assignments`. The order's item meta should also carry `tier_slug` per line item.
- If the order was already processed once, check `tier_seats_applied` / `tier_seats_partial` meta on the order to confirm idempotency flags are set as expected.

**Multi-tier setup warning persists.**
- The warning shows when `tier_mode = true` and `tier_skus` is empty. Add an explicit `tier_skus` map and reload the page.
- The warning also names the expected form slug and tier-slug field parameter — confirm both match your Gravity Forms form.