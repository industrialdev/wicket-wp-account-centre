---
title: "Wicket Account Centre Documentation Index"
audience: [implementer, support, developer, end-user]
---

# Wicket WP Account Centre Documentation

## Product Docs (Operators & Support)
- [Overview](product/overview.md) — Plugin purpose, architecture, key concepts
- [ACC Options](product/acc-options.md) — Carbon Fields settings for org management, affiliation mode, permissions
- [Organization Management Overview](product/organization-management-general.md) — Core features, access control, Datastar UI
- [Organization Profile View](product/organization-profile-view.md) — Org profile display block
- [Organization Profile Edit](product/organization-profile-edit.md) — Org profile editing block
- [Organization Selector Shortcode](product/organization-selector-shortcode.md) — `[wicket_organization_selector]` shortcode

## Engineering Docs (Developers & Agents)
- [Base Block](engineering/base-block.md) — Blueprint for all ACC blocks: init class, template hierarchy, ACF config
- [Access Control & Roles](engineering/access-control.md) — MDP roles, WP sync, capability checks
- [Deprecated Functions](engineering/deprecated-functions.md) — Legacy functions and their `WACC()` replacements
- [Functions: WACC()](engineering/functions.md) — Service locator singleton and available services
- [Hooks: Filters & Actions](engineering/hooks.md) — All WordPress hooks exposed by the plugin
- [Plugin Entrypoint](engineering/plugin-entrypoint.md) — `class-wicket-acc-main.php` initialization flow
- [Wicket PHP SDK & MDP Integration](engineering/wicket-php-sdk.md) — `WACC()->Mdp()` abstraction layer
- [WooCommerce Integration](engineering/woocommerce.md) — WC endpoints in ACC, URL normalization, multilingual
- [MDP API](engineering/mdp-api.md) — Wicket REST API reference (authentication, pagination, filtering)
- [AC Additional Info Block](engineering/ac-additional-info.md) — Additional info schema management block
- [AC Callout Block](engineering/ac-callout.md) — Callout/renewal banner block
- [AC Individual Profile Block](engineering/ac-individual-profile.md) — Person profile display block
- [AC Manage Preferences Block](engineering/ac-manage-preferences.md) — Communication preferences block
- [AC Org Logo Block](engineering/ac-org-logo.md) — Organization logo block
- [AC Org Profile Block](engineering/ac-org-profile.md) — Organization profile display block
- [AC Org Search Select Block](engineering/ac-org-search-select.md) — Organization search/select block
- [AC Password Block](engineering/ac-password.md) — Password change block
- [AC Profile Picture Block](engineering/ac-profile-picture.md) — Profile photo upload block
- [AC Touchpoint: Cvent](engineering/ac-touchpoint-cvent.md) — Cvent event touchpoint block
- [AC Touchpoint: Event Calendar](engineering/ac-touchpoint-event-calendar.md) — Event calendar touchpoint
- [AC Touchpoint: Maple](engineering/ac-touchpoint-maple.md) — Maple LMS touchpoint block
- [AC Touchpoint: Microspec](engineering/ac-touchpoint-microspec.md) — Microspec touchpoint block
- [AC Touchpoint: Moodle](engineering/ac-touchpoint-moodle.md) — Moodle LMS touchpoint block
- [AC Touchpoint: Pheedloop](engineering/ac-touchpoint-pheedloop.md) — Pheedloop event touchpoint
- [AC Touchpoint: VitalSource](engineering/ac-touchpoint-vitalsource.md) — VitalSource touchpoint block
- [AC Touchpoint: Zoom](engineering/ac-touchpoint-zoom.md) — Zoom touchpoint block
- [AC Welcome Block](engineering/ac-welcome.md) — Greeting and membership list block

## Guides (End Users)
- [Change Your Profile Picture](guides/change-profile-picture.md) — Upload, update, or remove your profile photo
