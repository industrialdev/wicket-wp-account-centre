# ACC User Class Documentation

## Overview
The `User` class manages user-related functionality, synchronization between WordPress users and MDP persons, and handles user meta data within the Account Centre plugin.

## Class Definition
```php
namespace WicketAcc;

class User {
    /**
     * User meta keys for MDP integration
     */
    protected const META_KEYS = [
        'uuid' => '_wicket_person_uuid',
        'roles' => '_wicket_person_roles',
        'last_sync' => '_wicket_last_sync',
        'org_uuid' => '_wicket_org_uuid'
    ];

    /**
     * Constructor.
     * Sets up user synchronization hooks
     */
    public function __construct() {
        add_action('user_register', [$this, 'sync_new_user']);
        add_action('profile_update', [$this, 'sync_user_update']);
        add_action('delete_user', [$this, 'handle_user_deletion']);
    }
}
```

## Core Methods

### User Synchronization
```php
/**
 * Synchronizes WordPress user with MDP person
 * Creates or updates MDP record
 *
 * @param int $user_id WordPress user ID
 * @return bool|WP_Error Sync status or error
 */
public function sync_user(int $user_id): bool|WP_Error;

/**
 * Gets MDP person data for user
 * Retrieves from cache or API
 *
 * @param int $user_id WordPress user ID
 * @return array|WP_Error Person data or error
 */
public function get_person_data(int $user_id): array|WP_Error;
```

### Meta Management
```php
/**
 * Sets user meta data
 * Handles MDP-specific meta
 *
 * @param int $user_id WordPress user ID
 * @param string $key Meta key
 * @param mixed $value Meta value
 * @return bool Success status
 */
protected function set_user_meta(int $user_id, string $key, $value): bool;

/**
 * Gets user meta data
 * Retrieves MDP-specific meta
 *
 * @param int $user_id WordPress user ID
 * @param string $key Meta key
 * @param bool $single Single value or array
 * @return mixed Meta value(s)
 */
protected function get_user_meta(int $user_id, string $key, bool $single = true);
```

### Role Management
```php
/**
 * Updates user roles
 * Syncs WordPress roles with MDP roles
 *
 * @param int $user_id WordPress user ID
 * @param array $roles MDP roles
 * @return bool Success status
 */
public function update_user_roles(int $user_id, array $roles): bool;

/**
 * Maps MDP roles to WordPress roles
 * Handles role translation
 *
 * @param array $mdp_roles MDP role list
 * @return array WordPress roles
 */
protected function map_roles(array $mdp_roles): array;
```

## Features

### Data Synchronization
- User creation sync
- Profile updates
- Role management
- Meta data handling
- Deletion handling

### Cache Management
```php
/**
 * Cache configuration for user data
 */
protected const CACHE_CONFIG = [
    'person_data' => [
        'duration' => 3600,  // 1 hour
        'group' => 'wicket_users'
    ],
    'meta' => [
        'duration' => 86400, // 24 hours
        'group' => 'wicket_user_meta'
    ]
];
```

### Error Handling
- Sync failures
- Invalid user data
- Missing MDP records
- Role mapping errors
- Meta update failures

## Integration Points
- WordPress user system
- MDP Person API
- Role management
- Meta data system
- Cache management
