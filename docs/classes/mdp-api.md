# ACC MDP API Class Documentation

## Overview
The `MDP_Api` class handles all interactions with the Member Data Platform (MDP) API. It manages authentication, request handling, and response processing for all MDP-related operations.

## Class Definition
```php
namespace WicketAcc;

class MDP_Api {
    /**
     * API configuration
     */
    protected array $config = [
        'endpoint' => '',
        'version' => 'v1',
        'timeout' => 30,
        'verify_ssl' => true
    ];

    /**
     * Constructor.
     * Sets up API configuration and authentication.
     */
    public function __construct();
}
```

## Core Methods

### API Communication
```php
/**
 * Sends request to MDP API
 * Handles authentication and response processing
 *
 * @param string $endpoint API endpoint
 * @param string $method HTTP method
 * @param array $data Request data
 * @return array|WP_Error Response data or error
 */
public function request(string $endpoint, string $method = 'GET', array $data = []): array|WP_Error;

/**
 * Processes API response
 * Handles errors and data formatting
 *
 * @param mixed $response Raw API response
 * @return array Processed response data
 */
protected function process_response($response): array;
```

### Authentication
```php
/**
 * Gets authentication token
 * Manages token caching and refresh
 *
 * @return string|WP_Error Valid token or error
 */
protected function get_auth_token(): string|WP_Error;

/**
 * Validates API credentials
 * Tests connection to MDP
 *
 * @return bool True if credentials are valid
 */
public function validate_credentials(): bool;
```

### Cache Management
```php
/**
 * Sets cache for API response
 *
 * @param string $key Cache key
 * @param mixed $data Data to cache
 * @param int $expiration Cache duration in seconds
 * @return bool Success status
 */
protected function set_cache(string $key, $data, int $expiration = 3600): bool;

/**
 * Gets cached API response
 *
 * @param string $key Cache key
 * @return mixed|false Cached data or false if not found
 */
protected function get_cache(string $key);
```

## API Endpoints

### Person Operations
```php
/**
 * Gets person data from MDP
 *
 * @param string $uuid Person UUID
 * @return array Person data
 */
public function get_person(string $uuid): array;

/**
 * Updates person data in MDP
 *
 * @param string $uuid Person UUID
 * @param array $data Updated person data
 * @return array Updated person data
 */
public function update_person(string $uuid, array $data): array;
```

### Organization Operations
```php
/**
 * Gets organization data from MDP
 *
 * @param string $uuid Organization UUID
 * @return array Organization data
 */
public function get_organization(string $uuid): array;

/**
 * Updates organization data in MDP
 *
 * @param string $uuid Organization UUID
 * @param array $data Updated organization data
 * @return array Updated organization data
 */
public function update_organization(string $uuid, array $data): array;
```

## Error Handling
- API connection failures
- Authentication errors
- Invalid responses
- Rate limiting
- Timeout handling
- SSL verification issues

## Usage Example
```php
$mdp_api = new MDP_Api();
try {
    $person_data = $mdp_api->request('people/' . $uuid);
} catch (Exception $e) {
    // Handle error
}
```
