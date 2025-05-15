# Role Checker Feature

## Overview
The Role Checker provides methods to determine a user's roles within organizations and memberships, supporting access control and UI decisions across the application.

## Technical Implementation

### Method Identification
- **Class**: `RoleChecker`
- **Namespace**: `\Wicket\AccountCentre\Authorization`

### Common Roles
- `administrator`, can access all features
- `membership_owner`, can access all features where they own the membership
- `org_editor`, can edit organization details
- `membership_manager`, can manage membership details and add or remove members
- `member`, can access basic member features and view-only organization details

### Data Structure
```php
[
    'system_roles' => [
        'string[]' // List of system-wide roles
    ],
    'organization_roles' => [
        'org_uuid' => [
            'roles' => ['string[]'], // List of roles in this organization
            'is_active' => 'boolean'
        ]
    ],
    'membership_roles' => [
        'membership_uuid' => [
            'roles' => ['string[]'], // List of roles in this membership
            'is_active' => 'boolean'
        ]
    ]
]
```

### Integration with MDP

#### Main Methods
```php
class RoleChecker {
    /**
     * Get all roles for a person in an organization
     */
    public static function getPersonOrganizationRoles(
        string $personUuid,
        string $organizationUuid
    ): array;

    /**
     * Check if person has specific roles in organization
     */
    public static function personHasOrganizationRoles(
        string $personUuid,
        string $organizationUuid,
        array $roles,
        bool $requireAll = false
    ): bool;

    /**
     * Get all roles for a person in a membership
     */
    public static function getPersonMembershipRoles(
        string $personUuid,
        string $membershipUuid
    ): array;
}
```

#### Required Legacy Functions
From the legacy codebase, we'll incorporate:

1. `wicket_orgman_get_person_current_roles_by_org_id()`
   - Primary source for organization roles
   - Will be refactored into `getPersonOrganizationRoles`

2. `wicket_orgman_role_check()`
   - Core role validation logic
   - Will be adapted into `personHasOrganizationRoles`

3. `wicket_orgman_person_has_org_roles()`
   - Additional role validation helper
   - Will be merged with `personHasOrganizationRoles`

#### Implementation Notes
- Methods will use proper type hints and return types
- Will implement caching for frequent role checks
- Will handle both organization and membership contexts
- Will separate system roles from context-specific roles

### Usage Example
```php
// Check roles in organization context
$roleChecker = new RoleChecker();

// Get all roles
$roles = $roleChecker->getPersonOrganizationRoles(
    $personUuid,
    $organizationUuid
);

// Check specific roles
$canEdit = $roleChecker->personHasOrganizationRoles(
    $personUuid,
    $organizationUuid,
    ['org_editor', 'administrator']
);

// Get membership roles
$membershipRoles = $roleChecker->getPersonMembershipRoles(
    $personUuid,
    $membershipUuid
);
```

### Error Handling
```php
try {
    $roles = $roleChecker->getPersonOrganizationRoles($personUuid, $orgUuid);
} catch (PersonNotFoundException $e) {
    // Handle person not found
} catch (OrganizationNotFoundException $e) {
    // Handle organization not found
} catch (Exception $e) {
    // Handle unexpected errors
}
```

## Legacy Functions to be Refactored
- `wicket_orgman_get_person_current_roles_by_org_id()`
- `wicket_orgman_role_check()`
- `wicket_orgman_person_has_org_roles()`
