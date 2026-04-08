# Wicket Account Centre (ACC) Documentation

## Purpose
The Account Centre (ACC) plugin serves as the central hub for member and organization management in WordPress. It bridges WordPress with the Wicket Member Data Platform (MDP), providing a suite of interactive blocks and features for:
- Individual Profile Management
- Organization Management (Roster, Profiles, Documents)
- WooCommerce Integration (Subscriptions, Orders, Payment Methods)
- Third-party Touchpoints (Cvent, Moodle, Zoom, etc.)

## Technical Architecture

### Core Stack
- **PHP 8.2+**: Modern PHP features with strict typing.
- **PSR-4 Autoloading**: Managed via Composer (`WicketAcc\` namespace).
- **Advanced Custom Fields (ACF) Pro**: Powering the custom blocks.
- **Datastar**: Real-time, hypermedia-driven UI updates for dynamic components.
- **TailwindCSS**: Utility-first styling with theme variable integration.
- **MDP API**: The primary data source for all member and organization records.

### Plugin Structure
- `src/`: Core logic and service classes (PSR-4).
- `includes/`: Legacy helpers and block definitions (ACF).
- `templates-wicket/`: HTML templates for blocks and pages.
- `assets/`: Frontend CSS and JS.
- `docs/`: Technical and feature documentation.

### Core Services
The plugin uses a service-oriented architecture accessed via the `WACC()` singleton:
- `WACC()->Mdp()`: MDP API integration.
- `WACC()->Profile()`: Individual user profile management.
- `WACC()->OrganizationManagement()`: Core organization logic.
- `WACC()->OrganizationRoster()`: Organization member management.
- `WACC()->WooCommerce()`: Integration for WC endpoints.
- `WACC()->Blocks()`: Custom block registration and logic.

## Key Concepts

### Datastar Integration
Most dynamic interactions (profile updates, member list filtering, organization switching) use **Datastar**. This allows for a fast, "app-like" feel without full page reloads, using server-rendered HTML fragments.

### Theme Variables
Styling is strictly controlled via CSS variables. Custom styles should always reference variables from `/uploads/wicket-theme/css/theme-variables.css` to ensure brand consistency.

### Security
- **Nonce Validation**: All state-changing actions require a valid WordPress nonce.
- **Capability Mapping**: WP roles and capabilities are mapped to MDP permissions.
- **Sanitization**: All input/output is sanitized and escaped according to WP standards.

## Documentation Links
- [Plugin Entrypoint](../engineering/plugin-entrypoint.md)
- [Global Helper: WACC()](../engineering/functions.md)
- [Wicket PHP SDK & MDP Integration](../engineering/wicket-php-sdk.md)
- [Developer Hooks (Filters & Actions)](../engineering/hooks.md)
- [Organization Management Overview](./organization-management-general.md)
- [WooCommerce Integration](../engineering/woocommerce.md)
- [Deprecated Functions](../engineering/deprecated-functions.md)
