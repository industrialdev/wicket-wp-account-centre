---
title: "WooCommerce Integration"
audience: [developer, agent]
php_class: WicketAcc\WicketAcc
source_files: ["src/WooCommerce.php", "src/WicketORM/Services/AdditionalSeatsService.php", "src/WicketORM/Helpers/GravityFormsHelper.php", "class-wicket-acc-main.php"]
---

# WooCommerce Integration

## Overview
The Wicket Account Centre plugin seamlessly integrates with WooCommerce to bring account-related features (orders, subscriptions, payment methods) into the central Account Centre interface.

## Supported Endpoints
The following WooCommerce endpoints are managed within the ACC:
- `orders`: List and view individual orders.
- `subscriptions`: Manage WC Subscriptions.
- `payment-methods`: Add, delete, or set default payment methods.
- `view-order`: Detailed order view.
- `view-subscription`: Detailed subscription view.
- `add-payment-method`: Form for adding new payment methods.

## Technical Architecture

### Endpoint Routing
- **Class**: `WicketAcc\WooCommerce`
- **Hook**: `register_acc_wc_endpoints` registers localized slugs for each WooCommerce feature.
- **Redirection**: Canonical redirects are bypassed for ACC endpoints via the `redirect_canonical` filter to ensure proper page resolution.

### URL Normalization
The plugin includes a `normalize_endpoint_url` method to handle cases where WooCommerce might generate redundant URL segments (e.g., `/my-account/my-account/orders/`). It ensures consistent, clean URLs across all languages.

### Integration with Datastar
Some WooCommerce actions (like deleting a payment method) are enhanced with Datastar to provide real-time updates without full page reloads.

## Multilingual Support
All WooCommerce endpoints are translated into:
- English (EN)
- French (FR)
- Spanish (ES)

Example slugs:
- `orders` (EN) -> `commandes` (FR) -> `pedidos` (ES)

## Templates
WooCommerce-specific views are rendered using:
- `templates-wicket/account-centre/page-wc.php`: The main wrapper for WC content within the ACC.
- Standard WooCommerce templates (overridden if present in the theme).

## Additional Seats (Single-SKU)

See [ADDITIONAL-SEATS.md](../ORM/product/ADDITIONAL-SEATS.md) for the full flow. In short:

- The supplemental-members callout links to a Gravity Forms form, which posts to WooCommerce with the additional-seats SKU.
- `OrgMan::addAdditionalSeatsDataToOrderItem()` attaches `membership_id` and `org_uuid` to each line item.
- `AdditionalSeatsService::processOrderCompletion()` (or the equivalent order-completion handler) reads those item meta values and bumps the matching `organization_memberships.max_assignments` by the line-item quantity.

## Additional Seats (Multi-Tier)

Multi-tier mode (`integrations.additional_seats.tier_mode = true`):

- Each tier resolves its own WooCommerce product (`tier_skus` map or `{sku}-{tier-slug}` derivation).
- `OrgMan::addAdditionalSeatsDataToOrderItem()` classifies the cart item by product and writes the `tier_slug` into line-item meta.
- `AdditionalSeatsService::processTierSeatLineItem()` fulfils per line item by bumping the matching tier's `organization_memberships.max_assignments`.
- Idempotency per line item uses the `tier_seats_applied` order-meta flag; partial-fulfilment retries use `tier_seats_partial`."
