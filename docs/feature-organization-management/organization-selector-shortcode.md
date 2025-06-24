# Organization Selector Shortcode

## Overview
The Organization Selector Shortcode provides a reusable way to implement organization selection across the Account Centre plugin. It serves as a foundational component that other blocks can utilize to maintain consistent organization context.

## Usage

### Basic Implementation
```php
[wicket_organization_selector]
```

### Advanced Implementation
```php
[wicket_organization_selector mode="cards" filter="active" show_count="true" callback="refreshContent"]
```

## Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `mode` | string | "cards" | Display mode: "cards", "list", or "dropdown" |
| `filter` | string | "all" | Filter organizations: "all", "active", "inactive" |
| `show_count` | boolean | false | Show organization count in header |
| `callback` | string | null | JavaScript function to call after selection |

## Display Modes

### Cards Mode
- Grid layout with organization cards
- Shows organization logo, name, and key metrics
- Best for dashboards and main navigation

### List Mode
- Compact list view
- Shows essential information
- Ideal for sidebars and navigation menus

### Dropdown Mode
- Simple dropdown selector
- Minimal space requirement
- Perfect for headers and tight spaces

## State Management
- Uses WordPress transients for persistence
- Maintains selection across page loads
- Automatically clears on user logout

## Events & Hooks

### Actions
```php
do_action('wicket/acc/organization_selected', $organization_id);
do_action('wicket/acc/organization_changed', $new_org_id, $old_org_id);
```

### Filters
```php
apply_filters('wicket/acc/organization_selector_modes', $modes);
apply_filters('wicket/acc/organization_selector_display', $html, $mode);
```

## Integration Example

### PHP Template
```php
<?php
// In your template or block
echo do_shortcode('[wicket_organization_selector mode="cards" filter="active"]');
?>
```

### JavaScript Callback
```javascript
// In your JavaScript file
function refreshContent(organizationId) {
    // Handle organization change
    htmx.trigger('#content-area', 'refresh', { organizationId: organizationId });
}
```

## Performance Considerations
- Implements caching for organization list
- Uses Datastar for efficient updates
- Minimizes API calls through state management

## Accessibility
- Follows WCAG 2.1 guidelines
- Includes proper ARIA labels
- Keyboard navigation support

## Security
- Validates all shortcode attributes
- Implements WordPress nonces
- Respects user capabilities

## Data Structure

### Organization Data
Each organization in the selector includes:
- ID
- Name
- Logo URL
- Status (active/inactive)
- Member count
- User role within organization
- Additional metadata based on display mode

### Cache Structure
```php
// Transient key format
$key = 'wicket_org_selector_' . get_current_user_id();

// Cached data structure
$cache = [
    'organizations' => [], // List of organizations
    'last_updated' => time(),
    'selected_id' => null,
];
```

## Access Control

#### Required Roles
- Any authenticated user with at least one organization membership can view this block
- No additional role requirements for basic view
- Role-based visibility for action links as specified in Action Links Logic

#### Validation Method
`OrganizationManager::validateUserAuthentication(): bool`

This method will:
1. Verify user is logged in
2. Verify user has a valid Wicket UUID
3. Return boolean indicating authentication status

## Legacy Functions to be Refactored
- `wicket_orgman_get_person_organizations()`
- `get_wicket_user_uuid()`
