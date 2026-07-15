---
title: "Wicket Account Centre Overview"
audience: [implementer, support, developer]
php_class: WicketAcc\WicketAcc
source_files: ["class-wicket-acc-main.php", "src/", "src/WicketORM/", "includes/"]
---

# Wicket Account Centre (ACC) Documentation

## Purpose

The Account Centre (ACC) plugin is the central hub for member and organization management in WordPress. It bridges WordPress with the Wicket Member Data Platform (MDP) and ships:

- A service locator (`WACC()`) for individual profile, organization, and WooCommerce integration code.
- A library of HyperFields + Datastar blocks for the account UI.
- An in-tree `WicketORM\` org-roster library at `src/WicketORM/` that injects roster-management UI into account pages and exposes the full config tree (`OrgManConfig`).

The `WicketORM\` namespace is booted by `wicket-wp-account-centre` itself (no separate plugin install required). Sites register overrides against `wicket/org-roster/config` from a child-theme config file.

## Technical Architecture

### Core Stack
- PHP 8.2+ with strict typing.
- PSR-4 autoloading (`WicketAcc\` namespace via Composer).
- Advanced Custom Fields (ACF) Pro powering the custom blocks.
- HyperFields / HyperBlocks for declarative settings and block registration.
- Datastar for real-time, hypermedia-driven UI updates.
- TailwindCSS with theme-variable integration for styling.
- MDP API as the primary data source.

### Plugin Structure
- `class-wicket-acc-main.php`: Plugin entrypoint and `WACC()` service locator.
- `src/`: Core logic and service classes (`WicketAcc\` namespace).
- `src/WicketORM/`: In-tree org-roster library (`WicketORM\` namespace).
- `includes/`: Legacy helpers, integrations, and block definitions (ACF).
- `templates-wicket/`: HTML templates for blocks and pages.
- `assets/`: Frontend CSS and JS.
- `docs/`: Technical and feature documentation.

### Core Services (`WACC()`)
- `WACC()->Mdp()`: MDP API integration.
- `WACC()->Profile()`: Individual user profile management (with the explicit `clear_profile_image()` delete flow).
- `WACC()->OrganizationManagement()`: Core organization logic.
- `WACC()->OrganizationProfile()`: Org profile fields and logo.
- `WACC()->OrganizationRoster()`: Member management facade.
- `WACC()->WooCommerce()`: WC endpoint integration.
- `WACC()->Blocks()`: Custom block registration and logic.
- `WACC()->Router()` / `WACC()->Shortcodes()` / `WACC()->Settings()` / `WACC()->Helpers()` / `WACC()->Log()` / `WACC()->Language()` / `WACC()->Assets()` / `WACC()->Notification()` / `WACC()->User()`: supporting services.

### OrgMan Services (in-tree)
The `WicketORM\` library has its own service layer that is **not** reached through `WACC()`. Highlights:
- `WicketORM\OrgMan` (singleton, booted at `after_setup_theme` priority 20).
- `Services/MemberService`, `Services/MembershipService`, `Services/OrganizationService`, `Services/PermissionService`, `Services/ContactService`, `Services/Strategies/*`, `Services/AdditionalSeatsService`, `Services/MemberExportService`, `Services/EngagementService`, `Services/BulkMemberUploadService`.
- `Controllers/*` registered through REST routes.
- `Helpers/PermissionHelper` (centralized role and owner-removal guard).
- `Helpers/TemplateHelper` (hypermedia endpoint under `?action=hypermedia&template=...`).

See the org-roster config (`wicket/org-roster/config`) and `OrgManConfig`
defaults for the full layout.

## Key Concepts

### Datastar Integration
Most dynamic interactions (profile updates, member list filtering, organization switching) use **Datastar**. This allows for a fast, app-like feel without full page reloads, using server-rendered HTML fragments and SSE.

### OrgMan Page Injection
`OrgMan` injects library content on these page slugs:
- `organization-management`
- `organization-profile`
- `organization-members`
- `organization-members-bulk`
- `organization-contacts` (when `contacts.enabled = true`)
- `supplemental-members`

Legacy ACC compatibility slugs (`org-management`, `org-management-profile`, `org-management-members`, `org-management-roster`) are routed to the same templates.

### Multi-Tier Additional Seats
When `integrations.additional_seats.tier_mode = true`, the additional-seats flow resolves one WooCommerce product per membership tier. Order completion is fulfilled per line item with idempotency (`tier_seats_applied`) and partial-fulfilment retry safety (`tier_seats_partial`).

### Theme Variables
Styling is strictly controlled via CSS variables. Custom styles should always reference variables from `/uploads/wicket-theme/css/theme-variables.css` to ensure brand consistency.

### Security
- **Nonce Validation**: All state-changing actions require a valid WordPress nonce.
- **Capability Mapping**: WP roles and capabilities are mapped to MDP permissions.
- **Owner removal**: Enforced uniformly through `access.permissions.prevent_owner_removal` and `access.permissions.owner_removal_requires_membership_owner_role` via `WicketORM\Helpers\PermissionHelper::guardOwnerRemoval()`.
- **Sanitization**: All input/output is sanitized and escaped according to WP standards.

## Documentation Links
- [ACC Options (Settings)](./acc-options.md)
- [Organization Management Overview](./organization-management-general.md)
- [Organization Profile View](./organization-profile-view.md)
- [Organization Profile Edit](./organization-profile-edit.md)
- [Organization Selector Shortcode](./organization-selector-shortcode.md)
- [Change Your Profile Picture](./change-profile-picture.md)