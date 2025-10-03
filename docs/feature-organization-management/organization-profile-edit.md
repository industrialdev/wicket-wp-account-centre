# Organization Profile Edit Block

## Overview
The Organization Profile Edit block provides an interface for authorized users to modify organization information in the MDP system. It mirrors the view block's data structure while adding form validation and update capabilities.

## Block Identification
- **Slug**: `wicket-ac/organization-edit-info`
- **Name**: Edit organization information

## Access Control

### Required Roles
Users must have at least one of these roles to access the block:
- administrator
- org_editor
- membership_owner

### Validation Method
`OrganizationManager::validateEditAccess(string $organizationUuid): bool`

## Form Structure

### Basic Information Form
```php
[
    'name' => [
        'type' => 'text',
        'required' => true,
        'maxLength' => 255
    ],
    'alternate_name' => [
        'type' => 'text',
        'required' => false,
        'maxLength' => 255
    ],
    'type' => [
        'type' => 'select',
        'required' => true,
        'options' => 'getOrganizationTypes()'
    ],
    'parent_org' => [
        'type' => 'select',
        'required' => false,
        'options' => 'getAvailableParentOrganizations()'
    ],
    'status' => [
        'type' => 'select',
        'required' => true,
        'options' => ['active', 'inactive', 'duplicate']
    ],
    'description' => [
        'type' => 'textarea',
        'required' => false
    ],
    'mdp_affiliation' => [
        'supported_modes' => [
            'type' => 'checkbox_group',
            'options' => [
                'direct' => 'Allow Direct Affiliation',
                'cascade' => 'Allow Cascade Affiliation',
                'group' => 'Allow Group Affiliation'
            ],
            'required' => true,
            'min_selected' => 1
        ],
        'group_settings' => [
            'type' => 'fieldset',
            'depends_on' => [
                'field' => 'mdp_affiliation.supported_modes',
                'value' => 'group'
            ],
            'fields' => [
                'allow_parent_groups' => [
                    'type' => 'boolean',
                    'label' => 'Allow Parent Groups',
                    'default' => true
                ],
                'allow_child_groups' => [
                    'type' => 'boolean',
                    'label' => 'Allow Child Groups',
                    'default' => true
                ],
                'max_group_depth' => [
                    'type' => 'number',
                    'label' => 'Maximum Group Hierarchy Depth',
                    'min' => 1,
                    'max' => 10,
                    'default' => 3
                ]
            ]
        ]
    ]
]
```

### Address Forms
```php
[
    'addresses' => [
        'primary' => [
            'details' => [
                'type' => 'textarea',
                'required' => true
            ],
            'type' => [
                'type' => 'select',
                'required' => true,
                'options' => 'getAddressTypes()'
            ]
        ],
        'secondary' => [
            'details' => [
                'type' => 'textarea',
                'required' => false
            ],
            'type' => [
                'type' => 'select',
                'required' => false,
                'options' => 'getAddressTypes()'
            ]
        ]
    ]
]
```

### Contact Information Forms
```php
[
    'emails' => [
        'type' => 'repeater',
        'fields' => [
            'address' => [
                'type' => 'email',
                'required' => true
            ],
            'type' => [
                'type' => 'select',
                'options' => 'getEmailTypes()'
            ]
        ]
    ],
    'phones' => [
        'type' => 'repeater',
        'fields' => [
            'number' => [
                'type' => 'tel',
                'required' => true
            ],
            'type' => [
                'type' => 'select',
                'options' => 'getPhoneTypes()'
            ]
        ]
    ]
]
```

### Web Presence Form
```php
[
    'web_presence' => [
        'website' => [
            'type' => 'url',
            'required' => false
        ],
        'facebook' => [
            'type' => 'url',
            'required' => false
        ],
        'twitter' => [
            'type' => 'url',
            'required' => false
        ],
        'linkedin' => [
            'type' => 'url',
            'required' => false
        ]
    ]
]
```

## Integration with MDP

### Main Method
`OrganizationManager::updateOrganizationProfile(string $organizationUuid, array $data): array`

### Data Submission Flow
1. Form data validation
2. Sanitization
3. MDP API update calls
4. Response handling
5. Cache invalidation
6. UI feedback

## Required Legacy Functions

## Form Validation
- Client-side validation using WordPress form validation
- Server-side validation before MDP API calls
- Custom validation for specific fields (e.g., email format, phone numbers)
- Required field enforcement
- Type validation for all inputs

## UI/UX Considerations
- Form sections match view layout for consistency
- Inline validation feedback
- Save button with loading state
- Success/error notifications

## Events & Hooks

### Actions
```php
// $profile_image_url: (string|null) The URL of the updated profile image, or null if not set.
do_action('wicket/acc/profile/edit/profile_image_updated', $profile_image_url);
```

# Legacy Functions to Refactored

  - Retrieve organization data for editing
## Integration Methods

### Main Methods
```php
class OrganizationProfileEditor {
    /**
     * Retrieves organization data for editing
     * Includes all editable fields and current values
     * Validates user has edit permissions
     *
     * @param string $organizationUuid Organization to edit
     * @return array Organization data with form structure
     * @throws UnauthorizedException If user lacks edit permission
     * @throws OrganizationNotFoundException If organization not found
     */
    public function getOrganizationForEdit(string $organizationUuid): array;

    /**
     * Updates organization profile data
     * Validates all fields before submission
     * Handles address and contact updates
     *
     * @param string $organizationUuid Organization identifier
     * @param array $data Updated organization data
     * @return array Result with success status and messages
     * @throws ValidationException If data fails validation
     * @throws ApiException If MDP update fails
     */
    public function updateOrganizationProfile(
        string $organizationUuid,
        array $data
    ): array;

    /**
     * Validates edit permissions for current user
     * Checks for required roles: administrator, org_editor, membership_owner
     *
     * @param string $organizationUuid Organization to check
     * @return bool True if user can edit
     */
    private function validateEditAccess(string $organizationUuid): bool;
}

class OrganizationProfileValidator {
    /**
     * Validates all organization profile fields
     * Checks required fields, formats, and dependencies
     *
     * @param array $data Profile data to validate
     * @return array Validation results with errors if any
     */
    public function validateProfileData(array $data): array;

    /**
     * Validates address information
     * Checks required fields and format per address type
     *
     * @param array $addresses Address data to validate
     * @return array Validation results for addresses
     */
    private function validateAddresses(array $addresses): array;

    /**
     * Validates contact information
     * Checks email format, phone numbers, and URLs
     *
     * @param array $contactInfo Contact data to validate
     * @return array Validation results for contact info
     */
    private function validateContactInfo(array $contactInfo): array;
}

class OrganizationProfileFormatter {
    /**
     * Formats profile data for API submission
     * Restructures form data to match API expectations
     *
     * @param array $formData Raw form submission data
     * @return array Formatted data for API
     */
    public function formatForApi(array $formData): array;

    /**
     * Formats API response for form display
     * Restructures API data to match form fields
     *
     * @param array $apiData Raw API response data
     * @return array Formatted data for form
     */
    public function formatForForm(array $apiData): array;
}

class OrganizationProfileException extends Exception {
    /**
     * Creates validation error with field details
     *
     * @param array $errors Field-specific error messages
     * @return self Exception instance with validation details
     */
    public static function validationFailed(array $errors): self;

    /**
     * Creates API communication error
     *
     * @param string $message Error message from API
     * @param int $code Error code from API
     * @return self Exception instance with API details
     */
    public static function apiError(string $message, int $code): self;
}
```
