---
title: "Wicket Account Centre Documentation Index"
audience: [implementer, support, developer, end-user]
---

# Wicket WP Account Centre Documentation

## Product Docs (Operators & Support)
- [Overview](product/overview.md) — Plugin purpose, architecture, key concepts
- [ACC Options](product/acc-options.md) — HyperFields settings for org management, affiliation mode, permissions
- [Organization Management Overview](product/organization-management-general.md) — Core features, access control, Datastar UI
- [Organization Profile View](product/organization-profile-view.md) — Org profile display block
- [Organization Profile Edit](product/organization-profile-edit.md) — Org profile editing block
- [Organization Selector Shortcode](product/organization-selector-shortcode.md) — `[wicket_organization_selector]` shortcode

## Engineering Docs (Developers & Agents)

### Cross-cutting
- [Base Block](engineering/base-block.md) — Blueprint for all ACC blocks: init class, template hierarchy, ACF config
- [Access Control & Roles](engineering/access-control.md) — MDP roles, WP sync, capability checks, owner-removal guard
- [Deprecated Functions](engineering/deprecated-functions.md) — Legacy functions and their `WACC()` replacements
- [Functions: WACC()](engineering/functions.md) — Service locator singleton and available services
- [Hooks: Filters & Actions](engineering/hooks.md) — All WordPress hooks exposed by the plugin (incl. org-roster)
- [Plugin Entrypoint](engineering/plugin-entrypoint.md) — `class-wicket-acc-main.php` initialization flow and OrgMan boot
- [Wicket PHP SDK & MDP Integration](engineering/wicket-php-sdk.md) — `WACC()->Mdp()` abstraction layer
- [WooCommerce Integration](engineering/woocommerce.md) — WC endpoints in ACC, single-SKU + multi-tier additional seats
- [MDP API](engineering/mdp-api.md) — Wicket REST API reference (authentication, pagination, filtering)

### Blocks
- [AC Additional Info Block](engineering/ac-additional-info.md) — Additional info schema management block
- [AC Callout Block](engineering/ac-callout.md) — Callout/renewal banner block
- [AC Individual Profile Block](engineering/ac-individual-profile.md) — Person profile display block (with `mdp_json_fields` / `mdp_json_sections`)
- [AC Manage Preferences Block](engineering/ac-manage-preferences.md) — Communication preferences block
- [AC Org Logo Block](engineering/ac-org-logo.md) — Organization logo block with 404 fallback chain
- [AC Org Profile Block](engineering/ac-org-profile.md) — Organization profile display/edit block (with `mdp_json_config`; `mdp_json_sections` is inert, kept only for visibility of previously-saved values)
- [AC Org Search Select Block](engineering/ac-org-search-select.md) — Organization search/select block
- [AC Password Block](engineering/ac-password.md) — Password change block (HyperBlocks)
- [AC Profile Picture Block](engineering/ac-profile-picture.md) — Profile photo upload block with explicit delete flow
- [AC Welcome Block](engineering/ac-welcome.md) — Greeting and membership list block
- [AC Touchpoint: Cvent](engineering/ac-touchpoint-cvent.md) — Cvent event touchpoint block
- [AC Touchpoint: Event Calendar](engineering/ac-touchpoint-event-calendar.md) — Event calendar touchpoint
- [AC Touchpoint: Maple](engineering/ac-touchpoint-maple.md) — Maple LMS touchpoint block
- [AC Touchpoint: Microspec](engineering/ac-touchpoint-microspec.md) — Microspec touchpoint block
- [AC Touchpoint: Moodle](engineering/ac-touchpoint-moodle.md) — Moodle LMS touchpoint block
- [AC Touchpoint: Pheedloop](engineering/ac-touchpoint-pheedloop.md) — Pheedloop event touchpoint
- [AC Touchpoint: VitalSource](engineering/ac-touchpoint-vitalsource.md) — VitalSource touchpoint block
- [AC Touchpoint: Zoom](engineering/ac-touchpoint-zoom.md) — Zoom touchpoint block

## Guides (End Users)
- [Change Your Profile Picture](guides/change-profile-picture.md) — Upload, update, or remove your profile photo

## ORM Subtree (Org Roster)

The `WicketORM\` org-roster library lives inside this plugin at `src/WicketORM/`. Its documentation lives in a separate subtree with its own index. See [ORM/index.md](ORM/index.md) for the full table of contents, or browse:

- [ORM: Configuration](ORM/product/CONFIGURATION.md) — Canonical config schema with every key, default, and migration map
- [ORM: Additional Seats](ORM/product/ADDITIONAL-SEATS.md) — Single-SKU and multi-tier additional seats flow
- [ORM: Setup](ORM/product/SETUP.md) — How a site activates and configures the org-roster feature from the child theme
- [ORM: Architecture](ORM/engineering/ARCHITECTURE.md) — Core runtime pieces, page injection, mutation paths, integrations
- [ORM: Strategies](ORM/engineering/STRATEGIES.md) — Roster management strategies (direct, cascade, groups, membership_cycle)