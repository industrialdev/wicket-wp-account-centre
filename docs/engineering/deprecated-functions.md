---
title: "Deprecated Functions"
audience: [developer, agent]
php_class: WicketAcc
source_files: ["src/"]
---

# Deprecated Functions Documentation

## Overview
This document tracks deprecated functions in the Wicket Account Centre plugin. These legacy functions are being replaced by methods within the service-oriented architecture accessed via `WACC()`.

## Service Mappings

| Legacy Function | Modern Replacement |
|                 |                    |
| `wicket_get_active_memberships()` | `WACC()->Mdp()->Membership()->getCurrentPersonActiveMemberships()` |
| `woo_get_active_memberships()` | `WACC()->Mdp()->Membership()->getCurrentUserWooActiveMemberships()` |
| `wicket_get_active_memberships_relationship()` | `WACC()->Mdp()->Membership()->getActiveMembershipRelationship()` |
| `wicket_acc_get_avatar()` | `WACC()->User()->getAvatar()` |
| `wicket_profile_widget_validation()` | `WACC()->Profile()->validateWidgetFields()` |

## Membership & Renewal Functions

### `is_renewal_period`
- **Status**: Deprecated 1.5.0
- **Replacement**: Pending in `WACC()->Mdp()->Membership()`
- **Purpose**: Checks if a membership is within its defined renewal window.

### `wicket_ac_memberships_get_product_link_data`
- **Status**: Deprecated 1.5.0
- **Replacement**: `WACC()->Mdp()->Membership()->getRenewalProductLinks()`
- **Purpose**: Generates WooCommerce cart links for membership renewals.

## UI & Menu Functions

### `wicket_acc_menu_walker` / `wicket_acc_menu_mobile_walker`
- **Status**: Deprecated 1.5.0
- **Replacement**: Use native WordPress navigation with custom block-based templates.
- **Purpose**: Custom menu walkers for the Account Centre navigation.

## Page Management

### `wicket_acc_alter_wp_job_manager_pages`
- **Status**: Deprecated 1.5.0
- **Replacement**: Managed via `WACC()->Router()` hooks.
- **Purpose**: Integrates ACC pages into the WP Job Manager settings.

## Technical Notes
- **Location**: Legacy functions are maintained in `includes/legacy.php`.
- **Warning**: Using these functions may trigger `_doing_it_wrong()` notices in debug mode.
- **Migration**: Always prefer the `WACC()->Service()->method()` pattern for new development."
