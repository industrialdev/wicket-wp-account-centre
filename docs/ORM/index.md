---
title: "Wicket Org Roster Documentation"
audience: [implementer, support, developer]
---

# Wicket Org Roster Documentation

> **Migration note (2026-06):** The `WicketORM\` code lives inside this
> plugin (`wicket-wp-account-centre`), at
> [`src/WicketORM/`](../../../src/WicketORM/) (namespace `WicketORM\`,
> unchanged). `wicket-wp-account-centre` is the sole provider: it boots
> `OrgMan` itself at `after_setup_theme` priority 20. Sites configure the
> feature through a small child-theme config override file (see
> [Setup](product/SETUP.md)).

## Recent Highlights

- **Multi-tier additional seats** (`integrations.additional_seats.tier_mode`): one WooCommerce product per membership tier, with per-tier seat fulfilment and idempotency. See [Additional Seats](product/ADDITIONAL-SEATS.md); a working client config example lives in Wicket Atlas (`atlas/components/orm-configs/`).
- **Contacts roster** (`contacts.enabled`): relationship-only roster for `president`, `ceo`, `treasurer`, etc. Renders on the `organization-contacts` page slug.
- **Centralized owner-removal guard** (`access.permissions.prevent_owner_removal`, `access.permissions.owner_removal_requires_membership_owner_role`): enforced through `WicketORM\Helpers\PermissionHelper::guardOwnerRemoval()` from every strategy.

## Product Docs (Operators & Support)
- [Additional Seats](product/ADDITIONAL-SEATS.md) — Single-SKU and multi-tier additional-seats flow: requirements, config, setup checklist, and troubleshooting
- [Configuration](product/CONFIGURATION.md) — Full canonical config schema with all paths, defaults, and migration map (includes `contacts`, `exports`, `engagement`, and tier-mode keys)
- [Setup](product/SETUP.md) — How a site activates and configures the org-roster feature from the child theme
- [Testing](product/TESTING.md) — Available verification commands and practical validation steps

## Engineering Docs (Developers & Agents)
- [Architecture](engineering/ARCHITECTURE.md) — Core runtime pieces, page injection, mutation paths, integrations
- [Backwards Compatibility](engineering/BACKWARDS-COMPATIBILITY.md) — Compatibility rules, additive defaults, breaking change definition
- [Config Filters Reference](engineering/config-filters.md) — Every WordPress filter this library applies or consumes, with source locations, config paths, types, and defaults
- [Design](engineering/DESIGN.md) — Design goals, UI shape, reactive layer, current constraints
- [Frontend](engineering/FRONTEND.md) — Template structure, hypermedia endpoint, assets, config flags for rendering
- [Specs](engineering/SPECS.md) — Supported strategy keys, account screens, implemented capabilities, runtime gaps
- [Strategies](engineering/STRATEGIES.md) — Roster management strategies (direct, cascade, groups, membership_cycle)

### Strategy Deep-Dives

Each strategy has a dedicated subdirectory covering its specification, logic, roles, permissions, card display, add/remove flows, bulk upload, seats assignment, and implementation plan.

- [`engineering/strategy-direct/`](engineering/strategy-direct/) — Direct assignment strategy
- [`engineering/strategy-cascade/`](engineering/strategy-cascade/) — Cascade strategy
- [`engineering/strategy-groups/`](engineering/strategy-groups/) — Groups strategy
- [`engineering/strategy-membership-cycle/`](engineering/strategy-membership-cycle/) — Membership cycle strategy

### Active Site Config Snapshots

Per-client config snapshots live in **Wicket Atlas** (`atlas/components/orm-configs/`), not in this repo — client details must not ship inside the plugin. A generic, client-free example remains here:

- [Exports & Engagement Example](engineering/configs/EXPORTS-ENGAGEMENT-EXAMPLE.md) — opt-in `exports` and `engagement` config
