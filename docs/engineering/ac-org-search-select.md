---
title: "Ac Org Search Select"
audience: [developer, agent]
php_class: WicketAcc
source_files: ["src/"]
---

# ACC Org. Search & Select Block

## Overview
The `ac-org-search-select` block allows users to search for existing organizations or create new ones, establishing relationships within the MDP.

## Features
- Search existing organizations by name.
- Create new organizations on the fly.
- Select/Remove organization associations.
- Grant Roster Management or Organization Editor roles upon selection.
- Conditional filtering by relationship types.

## ACF Configuration (Field Prefix: `orgss_`)

| Field | Type | Description |
|       |      |             |
| `search_mode` | `select` | Determines how searching is handled. |
| `search_org_type` | `select` | Restricts search to a specific organization type. |
| `enable_relationship_filtering` | `boolean` | If true, filters organizations by relationship type. |
| `relationship_type_upon_org_creation` | `text` | Relationship type assigned when creating a new org. |
| `disable_ability_to_create_new_orgentity` | `boolean` | Hides the "Create New Org" UI. |
| `grant_roster_management_on_next_purchase` | `boolean` | Grants roster management capabilities. |
| `grant_org_editor_role_on_selection` | `boolean` | Grants organization editor role to the user. |
| `name_singular` / `name_plural` | `text` | Custom labels for "Organization(s)". |

## Technical Implementation
- **Initialization**: Managed by `WicketAcc\Blocks\OrgSearchSelect\init`.
- **Component**: Renders via the `org-search-select` shared component.
- **Dynamic Behavior**: Uses `Datastar` for real-time searching and selection updates.

## Hooks & Filters
- `wicket/acc/blocks/org-search-select/results`: Filter search results before display.
- `wicket/acc/blocks/org-search-select/selection_saved`: Action triggered after a selection is persisted."
