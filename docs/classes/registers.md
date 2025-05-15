# ACC Registers Class Documentation

## Overview
The `Registers` class handles registration of custom post types, taxonomies, and REST API endpoints for the Wicket Account Centre plugin. It manages the plugin's integration points with WordPress core functionality.

## Class Definition
```php
namespace WicketAcc;

class Registers {
    /**
     * Constructor.
     * Sets up registration hooks
     */
    public function __construct() {
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
}
```

## Core Methods

### Post Type Registration
```php
/**
 * Registers custom post types
 * Includes Account Centre specific types
 *
 * @return void
 */
public function register_post_types(): void;

/**
 * Gets post type configurations
 * Defines settings for each type
 *
 * @return array Post type configurations
 */
protected function get_post_type_configs(): array;
```

### Taxonomy Registration
```php
/**
 * Registers custom taxonomies
 * For organizing Account Centre content
 *
 * @return void
 */
public function register_taxonomies(): void;

/**
 * Gets taxonomy configurations
 * Defines settings for each taxonomy
 *
 * @return array Taxonomy configurations
 */
protected function get_taxonomy_configs(): array;
```

### REST API Registration
```php
/**
 * Registers REST API endpoints
 * For Account Centre functionality
 *
 * @return void
 */
public function register_rest_routes(): void;

/**
 * Gets endpoint configurations
 * Defines routes and callbacks
 *
 * @return array Endpoint configurations
 */
protected function get_endpoint_configs(): array;
```

## Registration Configurations

### Post Types
```php
protected const POST_TYPES = [
    'wicket_organization' => [
        'public' => false,
        'show_ui' => true,
        'has_archive' => false,
        'supports' => ['title', 'editor', 'thumbnail']
    ],
    'wicket_membership' => [
        'public' => false,
        'show_ui' => true,
        'has_archive' => false,
        'supports' => ['title']
    ]
];
```

### REST Routes
```php
protected const REST_ROUTES = [
    'profile' => [
        'methods' => 'GET,POST',
        'callback' => 'handle_profile_request',
        'permission_callback' => 'check_profile_permission'
    ],
    'organization' => [
        'methods' => 'GET,POST,PUT',
        'callback' => 'handle_organization_request',
        'permission_callback' => 'check_organization_permission'
    ]
];
```

## Features

### Registration Support
- Custom post types
- Custom taxonomies
- REST API endpoints
- Permission callbacks
- Validation handlers

### Integration Points
- WordPress REST API
- Post type system
- Taxonomy system
- Custom capabilities
