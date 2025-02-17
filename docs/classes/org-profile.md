# ACC Organization Profile Class Documentation

## Overview
The `OrganizationProfile` class manages organization profile data and operations within the Account Centre. It handles organization data retrieval, updates, and member management through MDP integration.

## Class Definition
```php
namespace WicketAcc;

class OrganizationProfile {
    /**
     * Organization data cache duration
     */
    protected const CACHE_DURATION = 3600; // 1 hour

    /**
     * Constructor.
     * Sets up organization profile handling
     */
    public function __construct();
}
```

## Core Methods

### Profile Management
```php
/**
 * Gets organization profile data
 * Includes basic info and relationships
 *
 * @param string $uuid Organization UUID
 * @return array|WP_Error Organization data or error
 */
public function get_organization(string $uuid): array|WP_Error;

/**
 * Updates organization profile
 * Handles data validation and MDP sync
 *
 * @param string $uuid Organization UUID
 * @param array $data Updated profile data
 * @return array|WP_Error Updated organization data or error
 */
public function update_organization(string $uuid, array $data): array|WP_Error;
```

### Member Management
```php
/**
 * Gets organization members
 * Includes role and status information
 *
 * @param string $uuid Organization UUID
 * @param array $args Query arguments
 * @return array List of members with metadata
 */
public function get_members(string $uuid, array $args = []): array;

/**
 * Updates member role
 * Manages member permissions
 *
 * @param string $org_uuid Organization UUID
 * @param string $person_uuid Person UUID
 * @param string $role New role
 * @return bool Success status
 */
public function update_member_role(
    string $org_uuid,
    string $person_uuid,
    string $role
): bool;
```

### Permission Handling
```php
/**
 * Checks if user can edit organization
 * Validates against required roles
 *
 * @param string $uuid Organization UUID
 * @return bool True if user can edit
 */
public function can_edit_organization(string $uuid): bool;

/**
 * Gets allowed actions for user
 * Based on roles and permissions
 *
 * @param string $uuid Organization UUID
 * @return array List of allowed actions
 */
public function get_allowed_actions(string $uuid): array;
```

## Organization Roles
```php
protected const ROLES = [
    'administrator' => [
        'can_edit' => true,
        'can_manage_members' => true,
        'can_delete' => true
    ],
    'member' => [
        'can_edit' => false,
        'can_manage_members' => false,
        'can_delete' => false
    ],
    'org_editor' => [
        'can_edit' => true,
        'can_manage_members' => false,
        'can_delete' => false
    ]
];
```

## Cache Management
```php
/**
 * Gets cached organization data
 *
 * @param string $uuid Organization UUID
 * @return array|false Cached data or false
 */
protected function get_cached_org(string $uuid);

/**
 * Sets organization data cache
 *
 * @param string $uuid Organization UUID
 * @param array $data Organization data
 * @return bool Cache set success
 */
protected function set_org_cache(string $uuid, array $data): bool;
```

## Error Handling
- Invalid organization UUID
- Permission denied
- MDP sync failures
- Cache issues
- Member role conflicts
