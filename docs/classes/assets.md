# ACC Assets Class Documentation

## Overview
The `Assets` class manages all asset (scripts and styles) registration, enqueuing, and handling for the Wicket Account Centre plugin. It handles both admin and front-end assets, version management, and dependencies.

## Class Definition
```php
namespace WicketAcc;

class Assets {
    /**
     * Initializes assets management
     * Registers hooks for asset handling
     */
    public function __construct() {}
}
```

## Core Methods

### Asset Registration
```php
/**
 * Registers plugin scripts
 * Handles both admin and front-end scripts
 *
 * @return void
 */
public function register_scripts(): void;

/**
 * Registers plugin styles
 * Handles both admin and front-end styles
 *
 * @return void
 */
public function register_styles(): void;

/**
 * Localizes script data
 * Adds PHP variables to JavaScript
 *
 * @param string $handle Script handle
 * @return void
 */
protected function localize_script(string $handle): void;
```

### Asset Configuration
```php
[
    'scripts' => [
        'wicket-acc-core' => [
            'src' => 'dist/js/core.min.js',
            'deps' => ['jquery', 'wp-api-fetch'],
            'version' => WICKET_ACC_VERSION,
            'in_footer' => true
        ],
        'wicket-acc-blocks' => [
            'src' => 'dist/js/blocks.min.js',
            'deps' => ['wicket-acc-core'],
            'version' => WICKET_ACC_VERSION,
            'in_footer' => true
        ]
    ],
    'styles' => [
        'wicket-acc-main' => [
            'src' => 'dist/css/main.min.css',
            'deps' => [],
            'version' => WICKET_ACC_VERSION,
            'media' => 'all'
        ],
        'wicket-acc-blocks' => [
            'src' => 'dist/css/blocks.min.css',
            'deps' => ['wicket-acc-main'],
            'version' => WICKET_ACC_VERSION,
            'media' => 'all'
        ]
    ]
]
```

### Conditional Loading
```php
/**
 * Determines if assets should load
 * Checks current page and conditions
 *
 * @return bool True if assets should load
 */
protected function should_load_assets(): bool;

/**
 * Loads admin-specific assets
 * Only on plugin admin pages
 *
 * @param string $hook_suffix Current admin page
 * @return void
 */
public function load_admin_assets(string $hook_suffix): void;
```

### Dependencies
```php
/**
 * Handles third-party dependencies
 * Manages external libraries
 *
 * @return void
 */
protected function handle_dependencies(): void;

/**
 * Loads required WordPress dependencies
 *
 * @return array List of WP script handles
 */
protected function get_wp_dependencies(): array;
```

## Usage Examples

### Script Registration
```php
add_action('wp_enqueue_scripts', function() {
    $assets = new Assets();
    $assets->register_scripts();
});
```

### Admin Asset Loading
```php
add_action('admin_enqueue_scripts', function($hook_suffix) {
    $assets = new Assets();
    $assets->load_admin_assets($hook_suffix);
});
```

## Error Handling
- Missing files
- Version conflicts
- Dependency issues
- Loading conditions
- Script localization
