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
---

# Additional Seats

The additional-seats flow lets organization managers purchase extra member slots through a Gravity Forms + WooCommerce handoff, without leaving the account area.

## How It Works

1. A manager on the `supplemental-members` page clicks "Purchase Additional Seats".
2. The library stores the current membership ID and organization UUID in user meta and session.
3. The user is redirected to a Gravity Forms form (configured via `integrations.additional_seats.form_id`).
4. On form submission, WooCommerce adds the additional-seats product to the cart and redirects to checkout.
5. On order completion, `OrgMan` processes the order and updates the seat count for the organization membership.

**Fail-closed guarantee:** if the purchase context (membership ID + org UUID) cannot be persisted to user meta before the redirect, the URL is not generated and the flow does not proceed.

<!-- CRITICAL:ADDITIONAL_SEATS_START -->
Purchase form URL generation fails closed when purchase context user-meta cannot be persisted.
WordPress administrators see an in-page setup warning when additional-seats prerequisites are incomplete (missing purchasable SKU product, form mapping, or `supplemental-members` page).
<!-- CRITICAL:ADDITIONAL_SEATS_END -->

## Requirements

- WooCommerce active and configured
- Gravity Forms active
- A WooCommerce product with SKU matching `integrations.additional_seats.sku` (default: `additional-seats`)
- A Gravity Forms form with ID matching `integrations.additional_seats.form_id`
- The `supplemental-members` WordPress page slug must exist

## Enabling the Flow

By default, additional seats is disabled. Enable it in your bootstrap config filter:

```php
add_filter('wicket/org-roster/config', static function (array $config): array {
    $config['integrations']['additional_seats']['enabled']  = true;
    $config['integrations']['additional_seats']['sku']      = 'additional-seats';
    $config['integrations']['additional_seats']['form_id']  = 42; // your GF form ID
    $config['integrations']['additional_seats']['form_slug'] = 'additional-seats';

    return $config;
});
```

## Config Reference

All keys live under `integrations.additional_seats`. See [CONFIGURATION.md](CONFIGURATION.md) for the full schema.

| Config key | Default | Description |
|---|---|---|
| `enabled` | `false` | Master switch. Must be `true` for the flow to activate. |
| `sku` | `additional-seats` | WooCommerce product SKU for the seat purchase product. |
| `discount_sku` | `corporate-seat-discount` | SKU for the optional seat discount product. |
| `form_id` | `0` | Gravity Forms form ID. Must be set to a valid form. |
| `form_slug` | `additional-seats` | Gravity Forms form slug used in URL generation. |
| `min_quantity` | `1` | Minimum number of seats purchasable in one order. |
| `max_quantity` | `900` | Maximum number of seats purchasable in one order. |

## Admin Setup Warning

When `integrations.additional_seats.enabled` is `true` and any of the following are missing, WordPress administrators see an in-page warning on the `supplemental-members` page:

- No WooCommerce product with the configured SKU
- `form_id` is `0` or maps to no form
- The `supplemental-members` page does not exist

Non-admin users do not see the warning; they see the page as if additional seats were unavailable.

## Troubleshooting

**The "Purchase Additional Seats" button does not appear.**
- Confirm `integrations.additional_seats.enabled` is `true`.
- Confirm the current user has a role listed in `access.permissions.purchase_seat_roles` (default: `membership_owner`, `membership_manager`, `org_editor`).
- Confirm the `supplemental-members` page slug exists.

**The form redirects but the cart is empty.**
- Verify the WooCommerce product SKU matches `integrations.additional_seats.sku` exactly.
- Check WooCommerce logs for product lookup failures.

**Seat count does not update after order completion.**
- Confirm the order processed successfully in WooCommerce (check order status).
- Check PHP error logs for exceptions from `AdditionalSeatsService`.
- Verify membership ID and org UUID are stored in the order's item meta (`membership_id`, `org_uuid`).
