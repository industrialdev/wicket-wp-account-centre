# ACC Blocks Class Documentation

## Overview
The `Blocks` class manages ACF block registration, initialization, and handling for all Account Centre blocks. It provides the foundation for block loading, rendering, and integration with WordPress block editor.

## Class Definition
```php
namespace WicketAcc;

class Blocks {
    /**
     * Initializes block functionality
     * Registers ACF blocks and handlers
     */
    public function __construct() {
        add_action('acf/init', [$this, 'register_blocks']);
    }
}
```

## Core Methods

### Block Registration
```php
/**
 * Registers all Account Centre blocks
 * Sets up block configuration and templates
 *
 * @return void
 */
public function register_blocks(): void;

/**
 * Gets block configuration
 * Loads block settings from JSON files
 *
 * @param string $block_name Block identifier
 * @return array Block configuration
 */
protected function get_block_config(string $block_name): array;

/**
 * Initializes block instance
 * Creates block class and sets up rendering
 *
 * @param string $block_name Block identifier
 * @return void
 */
protected function init_block(string $block_name): void;
```

### Block Directory Structure
```php
[
    'blocks_path' => WICKET_ACC_PATH . 'includes/blocks',
    'block_directories' => [
        '_ac-base-block',
        'ac-additional-info',
        'ac-callout',
        'ac-individual-profile',
        'ac-manage-preferences',
        'ac-org-logo',
        'ac-org-profile',
        'ac-password',
        'ac-profile-picture',
        'ac-touchpoint-cvent',
        'ac-touchpoint-event-calendar',
        'ac-touchpoint-maple',
        'ac-touchpoint-microspec',
        'ac-touchpoint-pheedloop',
        'ac-touchpoint-vitalsource',
        'ac-welcome'
    ]
]
```

### Block Configuration
```php
/**
 * Gets default block settings
 * Applied to all ACC blocks
 *
 * @return array Default settings
 */
protected function get_default_settings(): array;

/**
 * Validates block configuration
 * Ensures required settings exist
 *
 * @param array $config Block configuration
 * @return bool True if valid
 */
protected function validate_block_config(array $config): bool;
```

### Block Loading
```php
/**
 * Loads block dependencies
 * Includes required files and assets
 *
 * @param string $block_path Block directory path
 * @return bool Success status
 */
protected function load_block(string $block_path): bool;

/**
 * Gets block class name
 * Converts block name to class name
 *
 * @param string $block_name Block identifier
 * @return string Fully qualified class name
 */
protected function get_block_class_name(string $block_name): string;
```

## Features

### Block Categories
```php
/**
 * Registers custom block categories
 *
 * @param array $categories Existing categories
 * @return array Modified categories
 */
public function register_block_categories(array $categories): array;
```

### Block Support
- ACF integration
- Template overrides
- Asset management
- AJAX handling
- Access control

## Error Handling
- Invalid configurations
- Missing dependencies
- Class loading failures
- Template errors
- Asset loading issues
