# Organization Profile View

## Overview
The Organization Profile View block displays comprehensive organization information pulled from the MDP system. This block provides a structured view of all essential organization details.

## Data Fields

### Basic Information
- Organization Name
- Alternate Name
- Organization Type (select field)
- Parent Organization (select field)
- Organization Status (select field)
- Description

### MDP Affiliation
- Affiliation Modes Supported
  - Direct Affiliation Status
  - Cascade Affiliation Status
  - Group Affiliation Status
- Associated Groups
  - Parent Groups
  - Child Groups
  - Group Hierarchy View

### Address Information
#### Primary Address
- Address Details
- Address Type (select field)

#### Secondary Address
- Address Details
- Address Type (select field)

### Contact Information
- Emails (multiple entries)
- Phone Numbers (multiple entries)

### Web Presence
- Website URL
- Social Media Links
  - Facebook URL
  - Twitter URL
  - LinkedIn URL

## Technical Implementation

### Organization Identification
- Primary identification via URL parameter `org_id`
- Child organization override via `child_org_id`
- Parameter handling follows WordPress standards

#### URL Parameter Logic
```php
/**
 * Get the active organization UUID from URL parameters
 * Prioritizes child_org_id if present
 */
public static function getActiveOrganizationUuid(): ?string
{
    $orgUuid = sanitize_text_field($_GET['org_id'] ?? '');
    $childOrgUuid = sanitize_text_field($_GET['child_org_id'] ?? '');

    return !empty($childOrgUuid) ? $childOrgUuid : $orgUuid;
}
```

### Main Methods
```php
class OrganizationManager {
    /**
     * Determines active organization UUID from URL parameters
     * Prioritizes child_org_id over org_id if present
     * Sanitizes input parameters
     *
     * @return string|null Organization UUID or null if not found
     */
    public static function getActiveOrganizationUuid(): ?string;

    /**
     * Retrieves complete organization profile data
     * Includes all related information and documents
     * Validates user access before retrieval
     *
     * @param string $organizationUuid Organization identifier
     * @return array Complete organization profile data
     * @throws UnauthorizedException If user lacks access
     * @throws OrganizationNotFoundException If organization not found
     */
    public static function getOrganizationProfile(string $organizationUuid): array;

    /**
     * Validates user access to organization data
     * Checks user roles against required permissions
     *
     * @param string $organizationUuid Organization to check access for
     * @return bool True if user has required access
     */
    public static function validateUserAccess(string $organizationUuid): bool;

    /**
     * Retrieves organization documents
     * Filters based on user permissions
     *
     * @param string $organizationUuid Organization identifier
     * @return array List of accessible documents
     */
    protected static function getOrganizationDocuments(string $organizationUuid): array;
}

class OrganizationData {
    /**
     * Formats address information for display
     * Combines address components into readable format
     *
     * @param array $addressData Raw address components
     * @return string Formatted address string
     */
    public static function formatAddress(array $addressData): string;

    /**
     * Validates and formats contact information
     * Ensures proper format for emails and phone numbers
     *
     * @param array $contactData Raw contact information
     * @return array Validated and formatted contact data
     * @throws ValidationException If data invalid
     */
    public static function formatContactInfo(array $contactData): array;

    /**
     * Processes and validates social media URLs
     * Ensures proper URL format and supported platforms
     *
     * @param array $socialLinks Social media URLs
     * @return array Validated social media links
     * @throws ValidationException If URLs invalid
     */
    public static function processSocialLinks(array $socialLinks): array;
}
```

### Block Identification
- **Slug**: `wicket-ac/organization-view-info`
- **Name**: View organization information

### Data Structure
```php
[
    'uuid' => 'string',
    'name' => 'string',
    'alternate_name' => 'string|null',
    'type' => [
        'uuid' => 'string',
        'name' => 'string'
    ],
    'parent_org' => [
        'uuid' => 'string',
        'name' => 'string'
    ],
    'status' => 'string', // 'active', 'inactive', or 'duplicate'
    'description' => 'string|null',
    'mdp_affiliation' => [
        'supported_modes' => [
            'direct' => 'boolean',
            'cascade' => 'boolean',
            'group' => 'boolean'
        ],
        'groups' => [
            'parent_groups' => [
                [
                    'uuid' => 'string',
                    'name' => 'string',
                    'member_count' => 'int'
                ]
            ],
            'child_groups' => [
                [
                    'uuid' => 'string',
                    'name' => 'string',
                    'member_count' => 'int',
                    'parent_group' => [
                        'uuid' => 'string',
                        'name' => 'string'
                    ]
                ]
            ]
        ]
    ],
    'addresses' => [
        'primary' => [
            'details' => 'string',
            'type' => 'string'
        ],
        'secondary' => [
            'details' => 'string',
            'type' => 'string'
        ]
    ],
    'contact' => [
        'emails' => [
            [
                'address' => 'string',
                'type' => 'string'
            ]
        ],
        'phones' => [
            [
                'number' => 'string',
                'type' => 'string'
            ]
        ]
    ],
    'web_presence' => [
        'website' => 'string|null',
        'facebook' => 'string|null',
        'twitter' => 'string|null',
        'linkedin' => 'string|null'
    ]
]
```

### Access Control

#### Required Roles
Users must have at least one of these roles to access the block:
- member
- administrator
- membership_manager
- membership_owner
- org_editor

#### Validation Method
`OrganizationManager::validateUserAccess(string $organizationUuid): bool`

This method will:
1. Verify user is logged in
2. Check if user has any of the required roles
3. Return boolean indicating access permission

### Integration with MDP

#### Main Method Name
`OrganizationManager::getOrganizationProfile(string $organizationUuid): array`

#### Data Retrieval, Pseudo-Code
```php
public static function getOrganizationProfile(string $organizationUuid): array
{
    // Validate user access
    if (!self::validateUserAccess($organizationUuid)) {
        return [];
    }

    // Get organization data
    $organizationData = self::getOrganizationInfoExtended($organizationUuid);

    // Get organization documents
    $organizationDocuments = self::getOrganizationDocuments($organizationUuid);

    // Merge data and return
    return array_merge($organizationData, $organizationDocuments);
}
```

#### Required Legacy Functions
The following functions from the old implementation will be incorporated:

1. `wicket_orgman_get_organization_info_extended()`
   - Primary source for organization data
   - Will be refactored into the main method

2. `wicket_orgman_get_organization_documents()`
   - Supporting function for document retrieval
   - Will be included as a protected method

3. `wicket_orgman_role_check()`
   - Required for permission validation
   - Will be adapted into an authorization helper

#### Implementation Notes
- All functionality will be moved to proper OOP structure
- Legacy functions will remain as wrappers temporarily
- New implementation will handle proper error responses
- Will include proper type declarations

## Permissions
- User must be logged in
- User must have one of the required roles: member, administrator, membership_manager, membership_owner, or org_editor
- Access validation occurs before any data retrieval
- Failed validation results in appropriate error messages

## Legacy Functions to be Refactored
- `wicket_orgman_get_organization_info_extended()`
- `wicket_orgman_get_organization_types()`
- `wicket_orgman_get_address_types()`
