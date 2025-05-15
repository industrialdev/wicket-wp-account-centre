# ACC Safeguard Class Documentation

## Overview
The `Safeguard` class provides security measures and access control functionality for the Wicket Account Centre plugin. It handles user authentication verification, role-based access control, and security checks for admin actions.

## Class Definition
```php
namespace WicketAcc\Admin;

class Safeguard {
    /**
     * Initializes safeguard functionality
     * Sets up security hooks and filters
     */
    public function __construct();
}
```

## Core Methods

### Authentication Checks
```php
/**
 * Validates admin user authentication
 * Verifies WordPress and MDP authentication
 *
 * @return bool True if properly authenticated
 */
public function validate_admin_auth(): bool;

/**
 * Checks for required capabilities
 * Verifies user has necessary permissions
 *
 * @param string|array $capabilities Required capability or array of capabilities
 * @return bool True if user has required capabilities
 */
public function verify_capabilities($capabilities): bool;
```

### Access Control
```php
/**
 * Controls access to admin pages
 * Redirects unauthorized users
 *
 * @param string $page_slug Admin page identifier
 * @return void
 */
public function guard_admin_page(string $page_slug): void;

/**
 * Validates AJAX requests
 * Checks nonce and capabilities
 *
 * @param string $action AJAX action name
 * @return bool True if request is valid
 */
public function validate_ajax_request(string $action): bool;
```

### Security Measures
```php
/**
 * Enforces security headers
 * Sets recommended HTTP headers
 *
 * @return void
 */
protected function set_security_headers(): void;

/**
 * Logs security events
 * Records unauthorized access attempts
 *
 * @param string $event Event identifier
 * @param array $context Additional event data
 * @return void
 */
protected function log_security_event(string $event, array $context = []): void;
```

## Security Features

### Access Rules
```php
[
    'required_capabilities' => [
        'manage_options',
        'edit_wicket_settings'
    ],
    'protected_pages' => [
        'wicket-settings',
        'wicket-organizations',
        'wicket-integrations'
    ],
    'security_headers' => [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-XSS-Protection' => '1; mode=block',
        'X-Content-Type-Options' => 'nosniff'
    ]
]
```

## Usage Examples

### Basic Access Check
```php
$safeguard = new Safeguard();
if ($safeguard->validate_admin_auth()) {
    // Proceed with admin action
}
```

### Page Protection
```php
$safeguard = new Safeguard();
$safeguard->guard_admin_page('wicket-settings');
```

### AJAX Validation
```php
$safeguard = new Safeguard();
if ($safeguard->validate_ajax_request('update_settings')) {
    // Process AJAX request
}
```

## Error Handling
- Authentication failures
- Invalid capabilities
- Unauthorized access attempts
- AJAX request validation
- Security event logging
