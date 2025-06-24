# Organization Info-Card Block

## Overview
The Info-Card block provides a summary view of the current organization including membership details and navigation elements.

## Block Identification
- **Slug**: `wicket-ac/organization-info-card`
- **Name**: Organization Information Card

## Block Access
- Block content visible to all authenticated users
- No specific role requirements for viewing
- Navigation tab visibility still controlled by user roles
- Child organization access respects user permissions

## Display Requirements

### Main Card Content
1. Organization name displayed prominently at the top
2. Membership tier name shown below organization name
3. Membership owner full name displayed
4. Seat usage counter showing format: "X of Y seats used"

### Child Organizations Section
- If org has 5 or fewer children:
  - Display as vertical list
  - Each child org is clickable and links to its profile
  - Show active/inactive status indicator
- If org has more than 5 children:
  - Display as dropdown selector
  - Include option to navigate to selected child org
  - Include count of total children in label

### Navigation Tabs
Located below the main card:
1. Organization Profile tab
   - Always visible
   - Links to organization profile view
2. Manage Members tab
   - Visible based on user permissions
   - Links to member management interface
3. View Child Organizations tab
   - Only visible when organization has children
   - Links to child organizations listing

## Data Structure
```php
[
    'organization' => [
        'uuid' => 'string',
        'name' => 'string',
        'membership' => [
            'tier_name' => 'string',
            'owner' => [
                'uuid' => 'string',
                'full_name' => 'string',
                'email' => 'string'
            ],
            'seats' => [
                'used' => 'int',
                'total' => 'int'
            ]
        ],
        'child_organizations' => [
            [
                'uuid' => 'string',
                'name' => 'string',
                'active' => 'boolean'
            ]
        ]
    ],
    'navigation' => [
        'tabs' => [
            [
                'id' => 'profile',
                'label' => 'Organization Profile',
                'url' => 'string',
                'visible' => 'boolean'
            ],
            [
                'id' => 'members',
                'label' => 'Manage Members',
                'url' => 'string',
                'visible' => 'boolean'
            ],
            [
                'id' => 'children',
                'label' => 'View Child Organizations',
                'url' => 'string',
                'visible' => 'boolean',
                'shown_when' => 'has_children'
            ]
        ]
    ]
]
```

## Integration Methods
```php
class OrganizationInfoCard {
    /**
     * Retrieves and formats organization data for display in info card
     * Includes membership details, seats, and child organizations
     *
     * @param string $organizationUuid Organization identifier
     * @return array Organization data with all required fields
     * @throws OrganizationNotFoundException When organization not found
     */
    public function getCardData(string $organizationUuid): array;

    /**
     * Calculates seat usage for organization membership
     * Considers active and grace period members
     *
     * @param string $membershipUuid Membership identifier
     * @return array Seats info with used and total counts
     */
    private function getSeatsInfo(string $membershipUuid): array;

    /**
     * Formats child organizations for display
     * Determines display mode based on number of children
     *
     * @param array $children Raw child organization data
     * @return array Formatted child organizations with display mode
     */
    private function formatChildOrganizations(array $children): array;

    /**
     * Builds navigation tabs based on user permissions and context
     *
     * @param string $organizationUuid Organization identifier
     * @return array Navigation tab configuration
     */
    private function buildNavigationTabs(string $organizationUuid): array;
}

class InfoCardPermissions {
    /**
     * Validates user's access to view members
     * Requires at least member role
     *
     * @param string $organizationUuid Organization to check against
     * @return bool True if user can view members
     */
    public function canViewMembers(string $organizationUuid): bool;

    /**
     * Checks if user can access child organizations
     * Available to all authenticated users with organization access
     *
     * @param string $organizationUuid Organization to check against
     * @return bool True if user can access children
     */
    public function canAccessChildren(string $organizationUuid): bool;
}

class InfoCardDisplay {
    /**
     * Determines display mode for child organizations
     * Uses list for 5 or fewer, selector for more
     *
     * @param array $children Child organization data
     * @return string 'list' or 'selector'
     */
    public function getChildrenDisplayMode(array $children): string;

    /**
     * Creates formatted seat usage string
     * Example: "15 of 20 seats used"
     *
     * @param int $used Number of seats used
     * @param int $total Total available seats
     * @return string Formatted usage string
     */
    public function formatSeatUsage(int $used, int $total): string;
}
```

### Required MDP Data Fetching
1. Authentication check (user is logged in)
2. Organization basic information
3. Membership details including tier and owner
4. Seat usage calculation
5. Child organizations list
6. User permissions for navigation visibility

### Data Flow
1. Verify user authentication
2. Get organization UUID from current context
3. Fetch organization data including membership
4. Calculate seat usage
5. Fetch and process child organizations
6. Build navigation based on permissions
7. Return structured data for rendering

### Authorization Flow
```php
public function validateAccess(): bool
{
    // Only check if user is logged in
    return is_user_logged_in();
}

public function getNavigationAccess(): array
{
    return [
        'profile' => true,  // Always visible
        'members' => $this->hasManageAccess(),  // Check role permissions
        'children' => $this->hasChildOrganizations()  // Check if children exist
    ];
}
```

## Required Legacy Functions
- `wicket_orgman_get_organization_children()`
- `wicket_orgman_get_membership_details()`

## State Management
- Active tab state must persist across page loads
- Child organization selector must maintain selected state
- Navigation URLs must include current organization context
- Seat usage must update when members are added/removed
