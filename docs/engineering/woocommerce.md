---
title: "Woocommerce"
audience: [developer, agent]
php_class: Wicket_ACC_Main
source_files: ["src/"]
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
- Standard WooCommerce templates (overridden if present in the theme)."
