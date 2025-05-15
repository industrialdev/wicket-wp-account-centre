# ACC Profile Class Documentation

## Overview
The `Profile` class manages individual user profile data and operations within the Account Centre. It handles personal information, preferences, and profile data synchronization with MDP.

## Class Definition
```php
namespace WicketAcc;

class Profile {
    /**
     * Profile data cache duration
     */
    protected const CACHE_DURATION = 1800; // 30 minutes

    /**
     * Constructor.
     * Sets up profile handling and hooks
     */
    public function __construct();
}
```

## Core Methods

### Profile Management
```php
/**
 * Gets user profile data
 * Includes personal info and preferences
 *
 * @param string $uuid Person UUID
 * @return array|WP_Error Profile data or error
 */
public function get_profile(string $uuid): array|WP_Error;

/**
 * Updates user profile data
 * Syncs with MDP and WordPress user
 *
 * @param string $uuid Person UUID
 * @param array $data Updated profile data
 * @return array|WP_Error Updated profile data or error
 */
public function update_profile(string $uuid, array $data): array|WP_Error;
```

### Address Management
```php
/**
 * Gets user addresses
 * Includes primary and additional addresses
 *
 * @param string $uuid Person UUID
 * @return array List of addresses
 */
public function get_addresses(string $uuid): array;

/**
 * Updates user address
 * Handles primary address flag
 *
 * @param string $uuid Person UUID
 * @param array $address_data Address information
 * @param bool $is_primary Set as primary address
 * @return array|WP_Error Updated address data or error
 */
public function update_address(
    string $uuid,
    array $address_data,
    bool $is_primary = false
): array|WP_Error;
```

### Profile Fields
```php
protected const REQUIRED_FIELDS = [
    'first_name',
    'last_name',
    'email',
    'primary_address'
];

protected const OPTIONAL_FIELDS = [
    'preferred_name',
    'phone',
    'additional_emails',
    'social_media',
    'bio'
];
```

### Validation Methods
```php
/**
 * Validates profile data
 * Checks required fields and formats
 *
 * @param array $data Profile data to validate
 * @return bool|WP_Error True if valid or error
 */
protected function validate_profile_data(array $data): bool|WP_Error;

/**
 * Validates address data
 * Checks required address fields
 *
 * @param array $address Address data to validate
 * @return bool|WP_Error True if valid or error
 */
protected function validate_address(array $address): bool|WP_Error;
```

### Cache Management
```php
/**
 * Gets cached profile data
 *
 * @param string $uuid Person UUID
 * @return array|false Cached data or false
 */
protected function get_cached_profile(string $uuid);

/**
 * Sets profile data cache
 *
 * @param string $uuid Person UUID
 * @param array $data Profile data
 * @return bool Cache set success
 */
protected function set_profile_cache(string $uuid, array $data): bool;
```

## Error Handling
- Missing required fields
- Invalid data formats
- MDP sync failures
- Cache issues
- Permission errors

## Integration Points
- WordPress user system
- MDP Person API
- Address validation
- Email verification
- Cache management
