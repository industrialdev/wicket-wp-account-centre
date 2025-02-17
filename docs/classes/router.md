# ACC Router Class Documentation

## Overview
The `Router` class manages URL routing, request handling, and navigation within the Account Centre plugin. It handles endpoint registration, request processing, and integration with WordPress rewrite rules.

## Class Definition
```php
namespace WicketAcc;

class Router {
    /**
     * Route configurations and endpoints
     */
    protected array $routes = [];

    /**
     * Constructor.
     * Sets up routing hooks and endpoint registration
     */
    public function __construct() {
        add_action('init', [$this, 'register_rewrites']);
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_action('template_redirect', [$this, 'handle_requests']);
    }
}
```

## Core Methods

### Route Registration
```php
/**
 * Registers rewrite rules
 * Sets up custom URL structures
 *
 * @return void
 */
public function register_rewrites(): void;

/**
 * Adds custom query variables
 * For route parameter handling
 *
 * @param array $vars Existing query vars
 * @return array Modified query vars
 */
public function register_query_vars(array $vars): array;
```

### Request Handling
```php
/**
 * Processes incoming requests
 * Routes to appropriate handlers
 *
 * @return void
 */
public function handle_requests(): void;

/**
 * Gets endpoint handler for route
 * Returns callable for processing request
 *
 * @param string $route Route identifier
 * @return callable|null Handler or null if not found
 */
protected function get_handler(string $route): ?callable;
```

### Route Configuration
```php
[
    'endpoints' => [
        'profile' => [
            'path' => 'account/profile',
            'handler' => 'handle_profile',
            'methods' => ['GET', 'POST']
        ],
        'organization' => [
            'path' => 'account/organization/{uuid}',
            'handler' => 'handle_organization',
            'methods' => ['GET', 'POST', 'PUT']
        ]
    ],
    'middleware' => [
        'auth' => 'check_authentication',
        'nonce' => 'verify_nonce',
        'permission' => 'check_permission'
    ]
]
```

### Response Methods
```php
/**
 * Sends JSON response
 * Handles error and success states
 *
 * @param mixed $data Response data
 * @param int $status HTTP status code
 * @return void
 */
protected function json_response($data, int $status = 200): void;

/**
 * Redirects to error page
 * When request handling fails
 *
 * @param string $error Error identifier
 * @param int $status HTTP status code
 * @return void
 */
protected function error_redirect(string $error, int $status = 302): void;
```

## Features

### URL Management
- Custom rewrite rules
- Parameter handling
- Query var registration
- Route matching

### Request Processing
- Method validation
- Parameter extraction
- Handler delegation
- Response formatting

### Middleware Support
- Authentication checks
- Nonce verification
- Permission validation
- Request filtering

## Error Handling
- Invalid routes
- Missing handlers
- Parameter validation
- Authentication failures
- Permission denials
