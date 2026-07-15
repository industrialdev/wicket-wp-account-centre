---
title: "Organization Management Overview"
audience: [implementer, support]
php_class: WicketAcc\WicketAcc
source_files: ["src/OrganizationManagement.php", "src/OrganizationProfile.php", "src/OrganizationRoster.php", "src/WicketORM/OrgMan.php"]
---

# Organization Management Overview

## Overview

The Organization Management module is the in-tree `WicketORM\` org-roster library, booted by `wicket-wp-account-centre` on `after_setup_theme` priority 20. It is a frontend for the Wicket Member Data Platform (MDP) and provides:

- A roster management surface for organization members (Direct, Cascade, Groups, Membership Cycle).
- An organization profile editor and viewer.
- A contacts roster (relationship-only, opt-in).
- A supplemental-members flow for purchasing additional seats (single-SKU and multi-tier).
- An async CSV member export (opt-in).
- An MDP engagement/donation summary on member cards (opt-in).

For the full feature list and runtime contract, consult the org-roster config
(`wicket/org-roster/config`) and the `OrgManConfig` defaults.

## Core Features

### 1. Organization Roster (Members)
- **Members List**: View and filter all members associated with an organization.
- **Member Addition**: Add new members with specific roles.
- **Role Management**: Assign or revoke roles (e.g., Membership Manager, Org. Editor) for individuals.
- **Status Tracking**: Monitor membership status and active relationships.
- **Strategy Selection**: Choose between `direct`, `cascade`, `groups`, and `membership_cycle` via `membership.strategy`.

### 2. Profile Management
- **Organization Profile View**: Detailed, read-only display of organization metadata.
- **Profile Editing**: Dynamic form-based editing for organization details (name, type, status).
- **Business Information**: Specialized management for tax IDs, business numbers, and classifications.

### 3. Document Management
- Upload and manage organization-specific documents.
- Permission-based access to sensitive files.
- Integration with WordPress media library for file handling.

### 4. Contacts Roster (Opt-In)
- Activated with `contacts.enabled = true`.
- Separate from the membership roster; tracks `president`, `president_elect`, `secretary`, `ceo`, `treasurer`, `main_contact` (configurable).
- Renders on the `organization-contacts` page slug.
- Auto-assigns and strips `org_editor` / `membership_manager` on add/remove.

### 5. Hierarchical Management
- **Subsidiaries**: View and manage parent-child relationships between organizations.
- **Organization Selector**: A global shortcode/component allowing users to switch context between different organizations they manage.

### 6. Additional Seats
- **Single-SKU** (default): one WC product per org.
- **Multi-tier** (opt-in): one WC product per membership tier. Triggered with `integrations.additional_seats.tier_mode = true` and configured via `tier_skus` / `tier_slug_field`.

## Technical Architecture

### Service Classes

- `WACC()->OrganizationManagement()`: Core business logic for organization operations.
- `WACC()->OrganizationProfile()`: Org profile fields and logo handling.
- `WACC()->OrganizationRoster()`: Member management facade (delegates to `WicketORM\OrgMan`).
- `WACC()->Mdp()->Organization()`: API wrapper for MDP organization endpoints.

The in-tree `WicketORM\OrgMan` orchestrator owns its own services and controllers in `src/WicketORM/` and is **not** accessed through `WACC()`.

### Dynamic Interaction (Datastar)
The organization management interface is built using **Datastar** for real-time, interactive updates. Actions like searching, filtering, and role changes are performed without full page reloads.

### Access Control
Permissions are determined by:
- **WordPress Capabilities**: Standard WP role checks.
- **MDP Roles**: Roles assigned to the person-organization relationship in the MDP (e.g., `org_editor`).
- **OrgMan config**: The canonical `access.permissions.*` keys (`prevent_owner_removal`, `owner_removal_requires_membership_owner_role`, role-only management access, etc.).
- **Global Settings**: Strategy and behavior configured in the org-roster config (`wicket/org-roster/config`).

## Documentation Links
- [ACC Options (Settings)](./acc-options.md)
- [Organization Profile View](./organization-profile-view.md)
- [Organization Profile Edit](./organization-profile-edit.md)
- [Organization Selector Shortcode](./organization-selector-shortcode.md)