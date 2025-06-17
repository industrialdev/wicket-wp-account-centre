# Organization Members Block

## Overview
The Organization Members block displays a paginated list of members within an organization's membership, with capabilities to manage member permissions and relationships based on user roles.

## Block Identification
- **Slug**: `wicket-ac/organization-members`
- **Name**: Organization Members List

## Block Settings

### Global Settings Reference
The following settings are controlled globally through ACC Options:
- MDP Affiliation Mode (direct/cascade/group)
- Relationship Types and Default Relationship
- Manager Permissions:
  - Adding relationships
  - Assigning security roles
  - Removing members

### Block-specific Settings

### Listing Settings

#### Pagination Configuration
- Number of results per page (default: 12)
- Additional results will be hidden behind pagination

#### Search Options
- Display Search: Enable/disable search functionality

### Visual Configuration

#### Card Display Settings
Select which details to display on member cards:

```php
$card_display_fields = [
    'salutation' => true,
    'suffix' => false,
    'post_nominal' => true,
    'designation' => false,
    'middle_name_initials' => true,
    'profile_image' => true,
    'member_id' => false,
    'confirmation_status' => true,
    'membership_status' => true,
    'title' => true,
    'job_function' => true,
    'person_type' => false
];
```

#### Message Customization
- Customize Empty State Messaging: Enable/disable custom message when no members are found
- Customize Max Assignment Messaging: Enable/disable custom message when maximum assignments are reached

### Listing Settings
```php
[
    'per_page' => [
        'type' => 'number',
        'required' => true,
        'default_value' => 12,
        'min' => 1,
        'max' => 100,
        'label' => 'Number of results',
        'instructions' => 'Additional results will be hidden behind pagination'
    ],
    'display_search' => [
        'type' => 'true_false',
        'default_value' => 0,
        'label' => 'Display Search'
    ]
]
```

### Card Display Settings
```php
[
    'display_fields' => [
        'type' => 'checkbox',
        'choices' => [
            'salutation' => 'Salutation',
            'suffix' => 'Suffix',
            'post_nominal' => 'Post-nominal',
            'designation' => 'Designation',
            'middle_name' => 'Middle Name/Initials',
            'profile_image' => 'Profile Image',
            'member_id' => 'Member ID',
            'confirmation_status' => 'Confirmation Status',
            'membership_status' => 'Membership Status',
            'title' => 'Title',
            'job_function' => 'Job Function',
            'person_type' => 'Person Type'
        ],
        'default_value' => [
            'profile_image',
            'confirmation_status',
            'membership_status',
            'title',
            'job_function'
        ]
    ],
    'empty_state_message' => [
        'type' => 'text',
        'default_value' => 'No members found',
        'conditional_logic' => [
            'field' => 'customize_empty_state',
            'operator' => '==',
            'value' => 1
        ]
    ],
    'max_assignment_message' => [
        'type' => 'text',
        'default_value' => 'Maximum number of members reached',
        'conditional_logic' => [
            'field' => 'customize_max_assignment',
            'operator' => '==',
            'value' => 1
        ]
    ],
    'customize_empty_state' => [
        'type' => 'true_false',
        'default_value' => 0,
        'label' => 'Customize Empty State Messaging'
    ],
    'customize_max_assignment' => [
        'type' => 'true_false',
        'default_value' => 0,
        'label' => 'Customize Max Assignment Messaging'
    ]
]
```

## Data Structure

### Member List Item
```php
[
    'uuid' => 'string',
    'full_name' => 'string',
    'email' => 'string',
    'company_role' => 'string',
    'account_confirmed' => 'boolean',
    'mdpAffiliationMode' => 'string', // 'direct', 'cascade', or 'group'
    'roles' => [
        'string[]' // List of role identifiers
    ],
    'relationship' => [
        'uuid' => 'string',
        'type' => 'string',
        'start_date' => 'string',
        'end_date' => 'string|null',
        'in_grace' => 'boolean',  // Indicates if member is in grace period
        'is_active' => 'boolean', // Computed based on dates and grace period
    ],
    'group' => [ // Only present if membership_type is 'group'
        'uuid' => 'string',
        'name' => 'string',
        'parent_group' => [
            'uuid' => 'string',
            'name' => 'string'
        ]
    ],
    'actions' => [
        'edit_permissions' => [
            'visible' => 'boolean',
            'disabled' => 'boolean',
            'modal_endpoint' => 'string'
        ],
        'remove' => [
            'visible' => 'boolean',
            'disabled' => 'boolean',
            'endpoint' => 'string'
        ]
    ]
]
```

### Pagination Meta Data
```php
[
    'pagination' => [
        'total_items' => 'int',
        'items_per_page' => 'int',
        'total_pages' => 'int',
        'current_page' => 'int',
        'has_next' => 'boolean',
        'has_prev' => 'boolean'
    ]
]
```

## Access Control

### Required Roles
Users must have at least one of these roles to access the block:
- member (view only)
- administrator (full access)
- membership_manager (full access)
- membership_owner (full access)
- org_editor (view only)

### Action Permissions
```php
[
    'view_members' => ['member', 'administrator', 'membership_manager', 'membership_owner', 'org_editor'],
    'edit_permissions' => ['administrator', 'membership_manager', 'membership_owner'],
    'remove_member' => ['administrator', 'membership_manager', 'membership_owner']
]
```

## Block Configuration

### Search Component
```php
[
    'search' => [
        'type' => 'text',
        'placeholder' => 'Search by name or email',
        'min_chars' => 3,
        'debounce' => 300, // milliseconds
        'endpoint' => '/wp-html/organization/{uuid}/members/search',
        'live_update' => true
    ]
]
```

### Display Settings
```php
[
    'per_page' => [
        'type' => 'number',
        'default' => 20,
        'min' => 10,
        'max' => 100
    ],
    'removal_mode' => [
        'type' => 'select',
        'options' => [
            'complete_removal' => 'Remove member and roles',
            'end_date' => 'Set end date to today'
        ],
        'default' => 'end_date'
    ]
]
```

## Membership Types

### Direct Membership
- Members are directly assigned to the organization
- Roles are assigned directly to the user
- Simplest form of membership management

### Cascade Membership
- Members are connected through relationship mapping
- MDP handles the membership connection internally
- Roles are still assigned to the user
- Useful for complex organizational structures

### Group Membership
- Members are assigned to groups
- Groups are associated with organizations
- Groups can have child groups
- Similar to direct membership but with group hierarchy
- Members inherit group associations

## Integration with MDP

### Main Methods
```php
class OrganizationMembers {
    /**
     * Retrieves paginated list of members for an organization
     * Includes relationship status and available actions
     * Calculates active status based on dates and grace period
     *
     * @param string $organizationUuid Organization identifier
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @return array Members list with pagination metadata
     * @throws OrganizationNotFoundException If organization not found
     */
    public function getMembers(
        string $organizationUuid,
        int $page = 1,
        int $perPage = 20
    ): array;

    /**
     * Updates permissions for a member in the organization
     * Validates role changes against user permissions
     *
     * @param string $personUuid Member identifier
     * @param string $organizationUuid Organization identifier
     * @param array $permissions New permission settings
     * @return bool Success status
     * @throws UnauthorizedException If user can't modify permissions
     */
    public function updateMemberPermissions(
        string $personUuid,
        string $organizationUuid,
        array $permissions
    ): bool;

    /**
     * Removes member from organization
     * Can either completely remove or set end date to today
     *
     * @param string $personUuid Member identifier
     * @param string $organizationUuid Organization identifier
     * @param string $removalMode 'complete_removal' or 'end_date'
     * @return bool Success status
     * @throws InvalidArgumentException If removal mode invalid
     */
    public function removeMember(
        string $personUuid,
        string $organizationUuid,
        string $removalMode
    ): bool;

    /**
     * Searches members in organization by name or email
     * Returns paginated results matching search criteria
     *
     * @param string $organizationUuid Organization identifier
     * @param string $query Search query string
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @return array Search results with pagination
     */
    public function searchMembers(
        string $organizationUuid,
        string $query,
        int $page = 1,
        int $perPage = 20
    ): array;

    /**
     * Determines if a membership is currently active
     * Considers end date and grace period status
     *
     * @param array $relationship Member relationship data
     * @return bool True if membership is active
     */
    private function isMembershipActive(array $relationship): bool;

    /**
     * Formats member data for display
     * Includes active status calculation
     *
     * @param array $results Raw member data
     * @return array Formatted member data
     */
    private function formatMemberResults(array $results): array;
}
```

### Search Implementation
```php
/**
 * Pseudo-code for member search
 */
public function searchMembers(string $organizationUuid, string $query): array
{
    try {
        if (strlen($query) < 3) {
            return $this->getMembers($organizationUuid);
        }

        $results = wicket_orgman_membership_search_members(
            $organizationUuid,
            [
                'query' => $query,
                'fields' => ['first_name', 'last_name', 'email'],
                'page' => 1,
                'limit' => 20
            ]
        );

        return $this->formatMemberResults($results);

    } catch (Exception $e) {
        log_error('Member search failed: ' . $e->getMessage());
        return [];
    }
}
```

### Required Legacy Functions
- `wicket_orgman_get_organization_members()`
- `wicket_orgman_update_member_permissions()`
- `wicket_orgman_end_relationship_today()`

### Datastar Integration

#### Search Input
```html
<input
    type="search"
    name="member_search"
    placeholder="Search by name or email"
    hx-get="/wp-html/organization/{uuid}/members/search"
    hx-trigger="keyup changed delay:300ms, search"
    hx-target="#members-list"
    hx-indicator=".search-indicator"
    hx-params="query"
    hx-swap="innerHTML"
/>
```

## UI Components

### Search Bar
- Real-time search with 300ms debounce
- Loading indicator during search
- Clear button to reset to default view
- Minimum 3 characters required
- Search status messages

### Member List
- Paginated table/list view
- Visual indicators for:
  - Active memberships
  - Expired memberships in grace period
  - Expired memberships (no grace)
- Sortable columns
- Search functionality
- Bulk action support

### Permissions Modal
```php
[
    'title' => 'Edit Member Permissions',
    'fields' => [
        'roles' => [
            'type' => 'checkbox_group',
            'options' => 'getAvailableRoles()',
            'current' => 'getCurrentRoles()'
        ],
        'relationship' => [
            'type' => 'select',
            'options' => 'getRelationshipTypes()',
            'current' => 'getCurrentRelationship()'
        ]
    ],
    'actions' => [
        'save' => [
            'label' => 'Save Changes',
            'endpoint' => 'updateMemberPermissions'
        ],
        'cancel' => [
            'label' => 'Cancel'
        ]
    ]
]
```

### Remove Member Dialog
```php
[
    'title' => 'Remove Member',
    'content' => [
        'message' => 'Are you sure you want to remove this member?',
        'warning' => 'This action cannot be undone.',
        'mode_selector' => [
            'type' => 'radio',
            'options' => [
                'complete_removal' => 'Remove member and all roles',
                'end_date' => 'Set relationship end date to today'
            ]
        ]
    ],
    'actions' => [
        'confirm' => [
            'label' => 'Remove Member',
            'endpoint' => 'removeMember'
        ],
        'cancel' => [
            'label' => 'Cancel'
        ]
    ]
]
```

### Pagination Component
- Datastar-powered pagination navigation
- Maintains search state across pages
- Loading indicator during page transitions
- Disabled states for pagination limits
- Current page indication
- Dynamic page number generation

## Protection Rules
1. Administrator and membership_owner roles cannot be removed
2. Users cannot remove themselves
3. Users cannot modify their own roles
4. All actions require CSRF verification, nonce validation
5. All endpoints validate user permissions

## Error Handling
- Invalid role assignments
- API communication errors
- Permission validation failures
- Member not found scenarios
- Invalid removal mode

## Legacy Functions to be Refactored
- `wicket_orgman_get_organization_members()`
- `wicket_orgman_membership_search_members()`
- `wicket_orgman_update_member_permissions()`
- `wicket_orgman_end_relationship_today()`
