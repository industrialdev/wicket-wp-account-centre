# ACC Admin Class Documentation

## Overview
The `AdminSettings` class manages the WordPress admin interface settings and configurations for the Wicket Account Centre plugin. It handles plugin settings pages, option management, and admin-specific functionality.

## Class Definition
```php
namespace WicketAcc;

class AdminSettings {
    /**
     * Initializes admin settings
     * Registers hooks and filters
     */
    public function __construct();
}
```

## Core Methods

### Settings Page Management
```php
/**
 * Adds plugin settings page to WordPress admin
 * Creates 'Wicket Account Centre' menu item
 *
 * @return void
 */
public function add_plugin_page(): void;

/**
 * Registers plugin settings
 * Sets up settings sections and fields
 *
 * @return void
 */
public function page_init(): void;

/**
 * Creates settings page HTML
 * Renders form and sections
 *
 * @return void
 */
public function create_admin_page(): void;
```

### Option Management
```php
/**
 * Sanitizes plugin options
 * Validates input before saving
 *
 * @param array $input Raw input values
 * @return array Sanitized options
 */
public function sanitize(array $input): array;

/**
 * Gets plugin option value
 *
 * @param string $key Option key
 * @param mixed $default Default value if not set
 * @return mixed Option value
 */
public function get_option(string $key, $default = false);

/**
 * Updates plugin option
 *
 * @param string $key Option key
 * @param mixed $value New value
 * @return bool Success status
 */
public function update_option(string $key, $value): bool;
```

### Section Callbacks
```php
/**
 * Renders settings section header
 *
 * @param array $args Section arguments
 * @return void
 */
public function section_info(array $args): void;

/**
 * Renders API settings section
 *
 * @param array $args Section arguments
 * @return void
 */
public function api_section_info(array $args): void;
```

### Field Renderers
```php
/**
 * Renders text input field
 *
 * @param array $args Field configuration
 * @return void
 */
public function render_text_field(array $args): void;

/**
 * Renders select dropdown field
 *
 * @param array $args Field configuration
 * @return void
 */
public function render_select_field(array $args): void;

/**
 * Renders checkbox field
 *
 * @param array $args Field configuration
 * @return void
 */
public function render_checkbox_field(array $args): void;
```

## Settings Structure

### Plugin Options
```php
[
    'general' => [
        'enable_features' => [
            'type' => 'checkbox',
            'default' => true
        ],
        'default_language' => [
            'type' => 'select',
            'options' => ['en', 'fr', 'es']
        ]
    ],
    'api' => [
        'endpoint_url' => [
            'type' => 'text',
            'required' => true
        ],
        'api_key' => [
            'type' => 'text',
            'required' => true
        ]
    ]
]
```

## Integration Points

### WordPress Admin
- Admin menu integration
- Settings API usage
- Option management
- Form handling

### Plugin Features
- Feature toggles
- API configuration
- Language settings
- Access control

## Error Handling
- Option validation
- API credential verification
- Permission checks
- Form submission errors

## Usage Examples

### Getting Options
```php
$admin = new AdminSettings();
$api_url = $admin->get_option('endpoint_url');
```

### Updating Settings
```php
$admin = new AdminSettings();
$success = $admin->update_option('enable_features', true);
```
