# Organization Subsidiaries Block

## Overview
Display and manage child organizations (subsidiaries/locations) for a parent organization, including their key information and management actions.

## Block Identification
- **Slug**: `wicket-ac/organization-subsidiaries`
- **Name**: Organization Subsidiaries/Locations

## Access Control

### Required Roles
- View access: any authenticated user
- Create subsidiary access:
  - administrator
  - membership_owner
  - org_editor

### Required Roles per Action
```php
[
    'view_list' => [
        'roles' => ['*'],  // All authenticated users
        'description' => 'View subsidiaries list'
    ],
    'edit_business_profile' => [
        'roles' => ['administrator', 'membership_owner', 'org_editor'],
        'description' => 'Edit subsidiary business profile'
    ],
    'manage_members' => [
        'roles' => ['administrator', 'membership_owner', 'membership_manager'],
        'description' => 'Manage subsidiary members'
    ],
    'create_subsidiary' => [
        'roles' => ['administrator', 'membership_owner', 'org_editor'],
        'description' => 'Add new child organization'
    ]
]
```

### Permission Validation
```php
class SubsidiaryPermissions {
    /**
     * Checks if user can edit subsidiary profile
     * Requires administrator, membership_owner, or org_editor role
     *
     * @param string $organizationUuid Organization to check against
     * @return bool True if user can edit profile
     */
    public function canEditProfile(string $organizationUuid): bool;

    /**
     * Checks if user can manage subsidiary members
     * Requires administrator, membership_owner, or membership_manager role
     *
     * @param string $organizationUuid Organization to check against
     * @return bool True if user can manage members
     */
    public function canManageMembers(string $organizationUuid): bool;

    /**
     * Checks if user can create new subsidiaries
     * Requires administrator, membership_owner, or org_editor role
     *
     * @param string $organizationUuid Organization to check against
     * @return bool True if user can create subsidiaries
     */
    public function canCreateSubsidiary(string $organizationUuid): bool;
}
```

## Data Structure

### Child Organization Item
```php
[
    'uuid' => 'string',
    'name' => 'string',
    'address' => [
        'formatted' => 'string',  // Full formatted address
        'type' => 'string'        // Address type (e.g., 'primary')
    ],
    'actions' => [
        'edit_profile' => [
            'url' => 'string',
            'visible' => 'boolean',  // Based on edit_business_profile roles
            'label' => 'Edit Business Profile'
        ],
        'manage_members' => [
            'url' => 'string',
            'visible' => 'boolean',  // Based on manage_members roles
            'label' => 'Manage Members'
        ]
    ],
    'header_actions' => [
        'create_subsidiary' => [
            'url' => 'string',
            'visible' => 'boolean',  // Based on create_subsidiary roles
            'label' => 'Add New Subsidiary'
        ]
    ]
]
```

### List Configuration
```php
[
    'pagination' => [
        'enabled' => true,
        'items_per_page' => 10,
        'current_page' => 'int',
        'total_pages' => 'int'
    ],
    'actions' => [
        'create_subsidiary' => [
            'url' => '/wp-html/organization/{uuid}/subsidiary/create',
            'visible' => 'boolean'  // Based on user permissions
        ]
    ]
]
```

## Integration with MDP

### Main Methods
```php
class OrganizationSubsidiaries {
    private $permissions;

    public function __construct() {
        $this->permissions = new SubsidiaryPermissions();
    }

    /**
     * Retrieves paginated list of child organizations for a parent organization
     * Includes organization details and available actions based on user permissions
     *
     * @param string $parentUuid Parent organization identifier
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @return array List of child organizations with pagination metadata
     * @throws OrganizationNotFoundException If parent organization not found
     */
    public function getChildOrganizations(
        string $parentUuid,
        int $page = 1,
        int $perPage = 10
    ): array;

    /**
     * Formats child organization response with permission-based actions
     * Adds edit and manage links based on user's roles
     *
     * @param array $children Raw child organization data
     * @param string $parentUuid Parent organization identifier
     * @return array Formatted child organizations with actions
     */
    private function formatChildrenResponse(
        array $children,
        string $parentUuid
    ): array;
}
```

## Display Requirements

### List Layout
1. Create Subsidiary button placement:
   - Top right of list
   - Only visible to users with creation permissions

2. Child Organization Entry:
   - Organization name as heading
   - Primary address below name
   - Action links on the right side
   - Visual separation between entries

3. Pagination:
   - Show when more than 10 items exist
   - Display current page and total pages
   - Previous/Next navigation
   - Page numbers for quick navigation

### Datastar Integration
```php
[
    'endpoints' => [
        'list' => [
            'url' => '/wp-html/organization/{uuid}/subsidiaries',
            'trigger' => 'load'
        ],
        'pagination' => [
            'url' => '/wp-html/organization/{uuid}/subsidiaries',
            'trigger' => 'click',
            'target' => '#subsidiaries-list',
            'indicator' => '.loading'
        ]
    ]
]
```

## UI Components

### Create Subsidiary Modal
```php
[
    'modal' => [
        'id' => 'create-subsidiary-modal',
        'title' => 'Create New Subsidiary',
        'trigger_button' => [
            'text' => 'Add New Subsidiary',
            'classes' => 'button button-primary',
            'attributes' => [
                'hx-get' => '/wp-html/organization/{uuid}/subsidiary/create/modal',
                'hx-target' => '#modal-container'
            ]
        ],
        'form' => [
            'endpoint' => '/wp-html/organization/{uuid}/subsidiary/create',
            'target' => '#create-subsidiary-result',
            'fields' => [
                'basic_info' => [
                    'name' => [
                        'type' => 'text',
                        'label' => 'Subsidiary Name',
                        'required' => true,
                        'maxLength' => 255
                    ],
                    'type' => [
                        'type' => 'select',
                        'label' => 'Organization Type',
                        'required' => true,
                        'options' => 'getOrganizationTypes()'
                    ],
                    'description' => [
                        'type' => 'textarea',
                        'label' => 'Description',
                        'required' => false
                    ]
                ],
                'address' => [
                    'building_name' => [
                        'type' => 'text',
                        'label' => 'Building Name',
                        'required' => false
                    ],
                    'department_name' => [
                        'type' => 'text',
                        'label' => 'Department Name',
                        'required' => false
                    ],
                    'division_name' => [
                        'type' => 'text',
                        'label' => 'Division Name',
                        'required' => false
                    ],
                    'street_address' => [
                        'type' => 'text',
                        'label' => 'Street Address',
                        'required' => true
                    ],
                    'unit' => [
                        'type' => 'text',
                        'label' => 'Apartment/Suite',
                        'required' => false
                    ],
                    'city' => [
                        'type' => 'text',
                        'label' => 'City/Town',
                        'required' => true
                    ],
                    'country' => [
                        'type' => 'select',
                        'label' => 'Country',
                        'required' => true,
                        'options' => 'getCountries()',
                        'trigger_update' => 'province_state'
                    ],
                    'province_state' => [
                        'type' => 'select',
                        'label' => 'Province/State',
                        'required' => true,
                        'options' => 'getProvincesByCountry(country)',
                        'depends_on' => 'country'
                    ],
                    'postal_code' => [
                        'type' => 'text',
                        'label' => 'Zip/Postal Code',
                        'required' => true,
                        'pattern' => '[A-Za-z0-9\\s-]*'
                    ]
                ],
                'contact' => [
                    'email' => [
                        'type' => 'email',
                        'label' => 'Email Address',
                        'required' => true
                    ],
                    'phone' => [
                        'type' => 'tel',
                        'label' => 'Phone Number',
                        'required' => false
                    ],
                    'website' => [
                        'type' => 'url',
                        'label' => 'Web Address',
                        'required' => false,
                        'placeholder' => 'https://'
                    ]
                ]
            ],
            'buttons' => [
                'submit' => [
                    'text' => 'Create Subsidiary',
                    'classes' => 'button button-primary'
                ],
                'cancel' => [
                    'text' => 'Cancel',
                    'classes' => 'button button-secondary',
                    'attributes' => [
                        'hx-on' => 'click: closeModal()'
                    ]
                ]
            ]
        ],
        'success_response' => [
            'message' => 'Subsidiary created successfully',
            'actions' => [
                'close_modal' => true,
                'refresh_list' => true
            ]
        ]
    ]
]
```

### Processing Methods
```php
class SubsidiaryCreation {
    /**
     * Creates a new subsidiary organization
     * Validates permissions and data before creation
     * Sets up parent-child relationship
     * Adds address and contact information
     *
     * @param string $parentUuid Parent organization identifier
     * @param array $subsidiaryData New subsidiary details
     * @return array Creation result with subsidiary data
     * @throws UnauthorizedException If user lacks permissions
     * @throws ValidationException If data is invalid
     */
    public function createSubsidiary(
        string $parentUuid,
        array $subsidiaryData
    ): array;

    /**
     * Formats address data for API submission
     * Converts from form structure to API structure
     *
     * @param array $addressData Raw address form data
     * @return array Formatted address data for API
     */
    private function formatAddress(array $addressData): array;

    /**
     * Adds contact information to newly created subsidiary
     * Handles email, phone, and website data
     *
     * @param string $subsidiaryUuid New subsidiary identifier
     * @param array $contactData Contact information to add
     * @throws ApiException If contact creation fails
     */
    private function addContactInfo(
        string $subsidiaryUuid,
        array $contactData
    ): void;
}
```

### Additional Required Legacy Functions
- `wicket_orgman_create_organization()`
- `wicket_orgman_set_parent_organization()`
- `wicket_orgman_create_or_update_organization_address()`
- `wicket_orgman_create_or_update_organization_email()`
- `wicket_orgman_create_or_update_organization_phone()`
- `wicket_orgman_create_or_update_organization_website()`
- `wicket_orgman_getCountries()`
- `wicket_orgman_getStatesProvinces()`

## Required Legacy Functions
- `wicket_orgman_get_organization_children()`
- `wicket_orgman_get_organization_info_extended()`
- `wicket_orgman_role_check()`

## Error States
1. No subsidiaries:
   - Display message: "No subsidiaries/locations found"
   - Show create button if user has permissions

2. Load failure:
   - Display error message
   - Provide retry option
   - Log error details

3. Permission denied:
   - Hide action buttons
   - Show appropriate message for unauthorized actions

## State Management
- Remember current page across sessions
- Maintain sort order if implemented
- Cache subsidiary list for performance
- Clear cache on subsidiary creation/update
