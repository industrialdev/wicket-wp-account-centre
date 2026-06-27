---
title: "Configuration Reorganization Plan"
audience: [developer]
source_files: ["src/Config/OrgManConfig.php"]
---

# Configuration Reorganization Plan

## Goal

Make the library configuration easier to understand for developers without breaking existing site overrides.

The current config array is valid, but it is organized by historical growth rather than feature ownership. This makes developers guess where a setting belongs and whether a key is domain behavior, UI, integration, or a legacy switch.

## Core Findings

- The main problem is not only `feature_flags`.
- The top-level config mixes domain rules, workflow settings, presentation options, and integration settings.
- Several features are split across multiple namespaces:
  - member management is spread across `member_addition`, `member_addition_form`, `member_edit`, and `bulk_upload`
  - relationship behavior is split across `relationships`, `relationship_types`, and `permissions.relationship_roles_map`
  - groups behavior contains `groups.ui`, while equivalent shared presentation settings live in top-level `ui`
- Some keys are obviously legacy-shaped:
  - `feature_flags.membership_resolution_prefer_current_cycle`
- Some top-level namespaces are technical concerns rather than product features:
  - `cache`

## Recommended Category Model

This model is now the target canonical schema. Breaking key renames are acceptable for the library refactor, provided the migration map is captured clearly for downstream sites.

### 1. Access And Authorization

Current paths:

- `roles.*`
- `role_labels.*`
- `permissions.*`

Why:

- These settings answer who can act, what roles mean, and how those roles should be presented.

### 2. Membership Model

Current paths:

- `roster.strategy`
- `feature_flags.membership_resolution_prefer_current_cycle`
- `membership_cycle.*`
- `seat_policy.*`

Why:

- These settings control how memberships are interpreted, resolved, limited, and mutated.

### 3. Relationships

Current paths:

- `relationships.*`
- `relationship_types.*`

Why:

- These settings define relationship defaults, filtering rules, and relationship labels.

Note:

- `permissions.relationship_roles_map.*` still lives under access because it grants capabilities, even though it references relationship types.

### 4. Member Management Workflows

Current paths:

- `member_addition.*`
- `member_addition_form.*`
- `member_edit.*`
- `bulk_upload.*`
- `edit_permissions_modal.*`

Why:

- These settings all shape the same lifecycle: adding, importing, and updating members.

### 5. Groups Strategy

Current paths:

- `groups.*`

Why:

- This is already a coherent strategy-specific feature area and should stay grouped.

### 6. Presentation

Current paths:

- `ui.*`

Why:

- Shared page, list, card, and messaging settings belong together.

### 7. Integrations And Delivery

Current paths:

- `additional_seats.*`
- `documents.*`
- `notifications.*`
- `business_info.*`

Why:

- These settings depend on external flows, delivery surfaces, or optional feature modules.

### 8. Platform And Operations

Current paths:

- `cache.*`

Why:

- Cache is an operational concern, not a business-domain concept.

## Migration Rule

- New category-owned keys are preferred over preserving awkward legacy paths.
- If a current key name is misleading, invert it or rename it.
- Every moved or renamed key must have a migration entry so site configs can be updated later.

## Phase Plan

### Phase 1. Canonical Schema Definition

- Rewrite `docs/CONFIGURATION.md` around the new canonical schema.
- Define the new top-level categories and the preferred new key names.
- Add a migration map from current runtime paths to new canonical paths.

Success criteria:

- A developer can find the right section by feature intent instead of guessing the raw array branch.
- Every current config path in `OrgManConfig` has a documented destination in the new schema.

### Phase 2. Ownership Map

- Build a key-to-consumer matrix from the codebase.
- Record which services and controllers consume which namespaces.
- Record cross-namespace coupling.

Success criteria:

- We know which namespaces are truly cohesive and which are accidental.

### Phase 3. Runtime Refactor

- Update the library runtime to read the canonical schema.
- Decide separately whether to keep a temporary compatibility layer for legacy paths during rollout.

Success criteria:

- Cleaner internal model with an explicit migration path for downstream sites.

## Non-Goals For This Pass

- No site-specific config cleanup in `docs/configs/`.
- No attempt to solve unrelated runtime issues while reorganizing configuration.

## Status: Completed

The configuration reorganization has been fully implemented in the library runtime.

- **Phase 1 (Documentation):** `docs/product/CONFIGURATION.md` was rewritten around the canonical schema and includes a migration map.
- **Phase 2 (Ownership):** Runtime consumers (services and controllers) have been updated to use the canonical paths.
- **Phase 3 (Runtime Refactor):** `OrgManConfig` now defines the canonical default tree, and `ConfigService` / `ConfigHelper` access these paths directly.

The library now uses the cleaner, feature-oriented configuration model. Site-specific overrides can be migrated using the migration map provided in the configuration documentation.
