---
title: "Organization Selector Shortcode"
audience: [implementer, support]
php_class: WicketAcc
---

# Organization Selector Shortcode

## Overview
The Organization Selector shortcode (`[wicket_organization_selector]`) provides a reusable interface for users to switch their current organization context. This is essential for users who manage multiple organizations through the Account Centre.

## Usage

### Basic Implementation
```php
[wicket_organization_selector]
```

### Advanced Implementation
```php
[wicket_organization_selector mode="dropdown" filter="active" show_count="true"]
```

## Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `mode` | string | "cards" | Display mode: `cards` (grid), `list` (simple list), or `dropdown`. |
| `filter` | string | "all" | Filter organizations: `all`, `active`, `inactive`. |
| `show_count` | boolean | false | Show the number of members in each organization. |
| `redirect` | string | "" | URL to redirect to after a new organization is selected. |

## Core Functionality

### 1. Context Switching
When a user selects an organization:
- **Parameter Update**: The `org_uuid` or `child_org_id` URL parameter is updated.
- **Session Persistence**: The selection is typically stored in the user's session or a WordPress transient to maintain context across pages.
- **Service**: `WACC()->Mdp()->Organization()->getOrganizationByUuid()`.

### 2. Multi-organization Display
The selector lists all organizations associated with the current user:
- **Service**: `WACC()->Mdp()->Organization()->getCurrentUserOrganizations()`.
- **Relationship Type**: Usually filtered to only show organizations where the user has management roles (`org_editor`, `membership_manager`).

## Technical Implementation

### Core Services
- `WACC()->Mdp()->Organization()`: API interaction for fetching organization lists.
- `WACC()->Shortcodes()`: Registration and handling of the `[wicket_organization_selector]` tag.
- `WACC()->OrganizationProfile()`: Retrieves logos for the card/list display.

### Dynamic Experience (Datastar)
The shortcode is enhanced with **Datastar** to provide real-time updates:
- **Live Filtering**: Filter the list as the user types in a search box.
- **Instant Switching**: Change organization context without a full page reload for supported blocks.

## Events & Hooks

### Actions
- `wicket/acc/organization_selected`: Triggered when an organization is clicked.
- `wicket/acc/organization_changed`: Triggered when the current organization context is successfully updated.

### Filters
- `wicket/acc/organization_selector_query_args`: Filter the MDP API query parameters.
- `wicket/acc/organization_selector_template`: Override the HTML template used for rendering.

## Access Control
- **Viewing**: Authenticated users who have at least one verified relationship with an organization.
- **Selection**: Restricted to organizations where the user has valid management roles.

## Security Best Practices
- **UUID Sanitization**: Always sanitize organization UUIDs before performing context switches.
- **Capability Checks**: Explicitly verify the user's role in the target organization before updating the session context.
- **Nonce Protection**: Context switches performed via AJAX must include a valid WordPress nonce."
