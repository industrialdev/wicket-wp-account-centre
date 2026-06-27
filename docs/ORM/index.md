# Wicket Org Roster Documentation

> **Migration note (2026-06):** The `WicketORM\` code lives inside this
> plugin (`wicket-wp-account-centre`), at
> [`src/WicketORM/`](../../../src/WicketORM/) (namespace `WicketORM\`,
> unchanged). `wicket-wp-account-centre` is the sole provider: it boots
> `OrgMan` itself at `after_setup_theme` priority 20. Sites configure the
> feature through a small child-theme config override file (see
> [Installation](product/INSTALLATION.md)).

## Product Docs (Operators & Support)
- [Additional Seats](product/ADDITIONAL-SEATS.md) — Additional-seats purchase flow: requirements, config, setup checklist, and troubleshooting
- [Configuration](product/CONFIGURATION.md) — Full canonical config schema with all paths, defaults, and migration map (includes `exports` for async CSV export and `engagement` for MDP data display)
- [Installation](product/INSTALLATION.md) — How account-centre boots `OrgMan` and how a site registers its config overrides
- [Testing](product/TESTING.md) — Available verification commands and practical validation steps

## Engineering Docs (Developers & Agents)
- [Architecture](engineering/ARCHITECTURE.md) — Core runtime pieces, page injection, mutation paths, integrations
- [Backwards Compatibility](engineering/BACKWARDS-COMPATIBILITY.md) — Compatibility rules, additive defaults, breaking change definition
- [Config Filters Reference](engineering/config-filters.md) — Every WordPress filter this library applies or consumes, with source locations, config paths, types, and defaults
- [Dead Code Audit](engineering/DEAD-CODE-AUDIT.md) — Unused classes, methods, and latent bugs in the current codebase (audited 2026-03-19)
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

Per-client bootstrap overrides for sites currently using this library. Manually maintained — update when a site override changes.

- [`engineering/configs/`](engineering/configs/) — CCHL, CSAE, ESCRS, IAA, MSA, NJBIA, PACE, Exports & Engagement Example

## Planning (Archive)
- [Active Sites Index](guides/ACTIVE-SITES.md) — Index of active site config snapshot files and their strategy mappings
- [Config Reorganization Plan](guides/CONFIG-REORGANIZATION-PLAN.md) — Planning document for config schema migration (historical)
