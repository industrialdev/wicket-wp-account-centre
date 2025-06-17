# AC Manage Preferences Block Documentation

## Overview
The Manage Preferences block allows users to control their communication preferences, privacy settings, and notification options. It synchronizes with MDP to maintain user preferences across platforms.

## Block Architecture

### Directory Structure
```
ac-manage-preferences/
├── block.json       # Block registration and settings
├── init.php        # Block initialization
├── render.php      # Template renderer
├── ajax.php        # AJAX handlers
├── block-styles.css # Preference styles
└── block-script.js  # Form handling
```

### Block Registration
```json
{
    "name": "wicket-ac/manage-preferences",
    "title": "Manage Preferences",
    "description": "Control user communication and privacy preferences",
    "category": "wicket-blocks",
    "icon": "admin-settings",
    "acf": {
        "mode": "preview",
        "renderTemplate": "render.php"
    },
    "supports": {
        "align": false,
        "jsx": true
    },
    "attributes": {
        "person_uuid": {
            "type": "string",
            "default": ""
        }
    }
}
```

## Core Functionality

### Block Class Methods
```php
/**
 * Manage Preferences block implementation
 * Extends base block functionality
 */
namespace WicketAcc\Blocks;

class ManagePreferences extends BaseBlock {
    /**
     * Sets up preference management functionality
     * Registers AJAX endpoints and form handlers
     */
    public function __construct();

    /**
     * Gets user preferences from MDP
     * Includes communication and privacy settings
     *
     * @param string $personUuid Person identifier
     * @return array User preferences data
     */
    protected function get_preferences(string $personUuid): array;

    /**
     * Updates user preferences in MDP
     * Handles validation and synchronization
     *
     * @param array $data Updated preferences
     * @return array Update status and messages
     */
    protected function update_preferences(array $data): array;

    /**
     * Validates access to preferences
     * Ensures user can only edit their own preferences
     *
     * @return bool True if user has access
     */
    protected function validate_access(): bool;
}
```

## Preference Categories

### Communication Preferences
```php
[
    'email_preferences' => [
        'newsletters' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Receive newsletters'
        ],
        'notifications' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Account notifications'
        ],
        'marketing' => [
            'type' => 'boolean',
            'default' => false,
            'label' => 'Marketing communications'
        ]
    ]
]
```

### Privacy Settings
- Directory visibility
- Profile information sharing
- Third-party data sharing
- Analytics participation

### Notification Settings
- Email frequency
- Push notifications
- SMS notifications
- Event reminders

## AJAX Endpoints

### Preference Update
```php
/**
 * Endpoint: /wp-html/person/{uuid}/preferences
 * Method: POST
 * Updates user preferences
 */
```

### Preference Fetch
```php
/**
 * Endpoint: /wp-html/person/{uuid}/preferences
 * Method: GET
 * Retrieves current preferences
 */
```

## Features

### Form Management
- Real-time updates
- Validation feedback
- Save indicators
- Reset options

### Data Synchronization
- MDP integration
- Cross-platform sync
- Cache management
- Conflict resolution

### UI Components
- Toggle switches
- Checkboxes
- Radio buttons
- Save/Reset buttons
- Status messages

## Error Handling
- Validation errors
- API failures
- Permission issues
- Sync conflicts
- Network timeouts

## Integration Points
- MDP Person API
- WordPress user settings
- Email service providers
- Notification systems

## Related Documentation
- [Base Block](/blocks/base-block.md)
