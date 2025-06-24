# AC Additional Info Block Documentation

## Overview
The Additional Info block provides an interface for managing and displaying additional information schemas for users or organizations. It supports both UUID-based and slug-based schema configurations, with the ability to override schemas based on date ranges.

## Block Architecture

### Directory Structure
```
ac-additional-info/
├── block.json          # Block registration and settings
├── init.php           # Main block implementation
├── render.php         # Template renderer
└── block-styles.css   # Block-specific styles
```

## Core Functionality

### Block Configuration Fields

#### Additional Info Schema
Repeater field that allows configuring multiple schemas:

```php
[
    'schema_slug' => string,          // When using slugs
    'schema_uuid_prod' => string,     // Production UUID
    'schema_uuid_stage' => string,    // Staging UUID
    'schema_use_override' => boolean,  // Enable schema override
    'schema_override_resource_slug' => string,  // Override resource slug
    'schema_override_resource_uuid_prod' => string, // Production override UUID
    'schema_override_resource_uuid_stage' => string, // Staging override UUID
    'show_as_required' => boolean,     // Mark fields as required
    'resource_override_activation_range' => [
        'date_range_from' => string,   // Start date for override
        'date_range_to' => string      // End date for override
    ]
]
```

#### Resource Type
Specifies the type of resource this schema applies to (e.g., 'person', 'organization')

#### Organization UUID
Optional organization UUID to associate the schema with

#### Use Slugs
Toggle between using slugs or UUIDs for schema identification

### Features

1. **Environment Support**
   - Separate UUIDs for production and staging
   - Automatic environment detection

2. **Schema Override System**
   - Override schemas with specific resources
   - Date-based activation ranges
   - Support for both slug and UUID based overrides

3. **Conditional Display**
   - Show/hide based on date ranges
   - Required field marking
   - Organization-specific schemas

4. **Integration**
   - Uses Wicket widget-additional-info component
   - Supports URL parameters for organization IDs
   - Child organization compatibility

### Access Control
Required roles for editing:
- administrator
- membership_owner
- org_editor

### Data Integration
- MDP API synchronization
- Cache management
- Batch updates
- Error recovery

### UI Components
- Collapsible sections
- Form validation feedback
- Save indicators
- Error messages
- Loading states

## AJAX Endpoints

### Section Update
```php
/**
 * Endpoint: /wp-html/organization/{uuid}/additional-info/{section}
 * Method: POST
 *
 * Updates section data in MDP
 * Handles validation and error responses
 */
```

### Section Fetch
```php
/**
 * Endpoint: /wp-html/organization/{uuid}/additional-info/{section}
 * Method: GET
 *
 * Retrieves section data and form fields
 * Returns rendered form template
 */
```

## Template Structure
- Section container with status
- Dynamic form fields
- Validation messages
- Action buttons
- Loading indicators

## Error Handling
- Form validation errors
- API communication failures
- Permission denied states
- Network timeout handling
- Data synchronization issues

## Related Documentation
- [Base Block](/blocks/base-block.md)
