# Organization Members Add Block

## Overview
Provides functionality to add new members to an organization through individual form submission or bulk CSV upload.

## Block Identification
- **Slug**: `wicket-ac/organization-members-add`
- **Name**: Add Organization Members

## Access Control

### Required Roles
Users must have at least one of these roles to access the block:
- administrator
- membership_manager
- membership_owner

## Data Structure

### Single Member Form Data
```php
[
    'first_name' => [
        'type' => 'text',
        'required' => true,
        'maxLength' => 255
    ],
    'last_name' => [
        'type' => 'text',
        'required' => true,
        'maxLength' => 255
    ],
    'email' => [
        'type' => 'email',
        'required' => true,
        'maxLength' => 255
    ],
    // Note: mdpAffiliationMode is now controlled globally through ACC Options
    'relationship_type' => [
        'type' => 'select',
        'required' => true,
        'options' => 'getVisibleRelationshipTypes()', // Gets allowed types from ACC Options
        'default_value' => 'getDefaultRelationshipType()' // Gets default from ACC Options
    ],
    'group' => [
        'type' => 'select',
        'required' => false,
        'options' => 'getAvailableGroups()',
        'depends_on' => [
            'field' => 'mdpAffiliationMode',
            'value' => 'group'
        ]
    ],
    'roles' => [
        'type' => 'checkbox_group',
        'required' => true,
        'options' => 'getAvailableRoles()',
        'exclude' => ['administrator', 'membership_owner'] // Protected roles
    ]
]
```

### CSV Upload Structure
```php
[
    'required_columns' => [
        'first_name',
        'last_name',
        'email',
        'mdpAffiliationMode' // 'direct', 'cascade', or 'group'
    ],
    'optional_columns' => [
        'roles', // Comma-separated list of role identifiers
        'group_uuid' // Required if membership_type is 'group'
    ],
    'max_file_size' => '2MB',
    'allowed_mime_types' => ['text/csv'],
    'sample_file_url' => '/templates/members-upload-template.csv'
]
```

## Integration with MDP

### Main Methods
```php
class OrganizationMembersAdd {
    /**
     * Adds a new member to an organization
     * If person doesn't exist in MDP, creates them first
     * Assigns specified roles to the person in the organization
     *
     * @param string $organizationUuid The organization to add member to
     * @param array $memberData Member information including name, email, roles
     * @return array Result with success status and member details
     * @throws ValidationException If member data is invalid
     * @throws DuplicateEmailException If email exists but can't be added
     */
    public function addMember(
        string $organizationUuid,
        array $memberData
    ): array;

    /**
     * Processes a CSV file containing multiple member entries
     * Validates CSV format and all member data before processing
     * Creates batch report of successes and failures
     *
     * @param string $organizationUuid Organization to add members to
     * @param string $filePath Path to uploaded CSV file
     * @return array Processing results with success and error counts
     * @throws InvalidFileException If CSV format is incorrect
     * @throws BatchProcessingException If bulk operation fails
     */
    public function processBulkUpload(
        string $organizationUuid,
        string $filePath
    ): array;

    /**
     * Searches MDP for existing person with given email
     * Used to prevent duplicate person creation
     *
     * @param string $email Email address to search for
     * @return array|null Person data if found, null if not
     * @throws ApiException If MDP search fails
     */
    private function findPersonByEmail(string $email): ?array;

    /**
     * Creates a new person record in MDP
     * Used when adding member who doesn't exist
     *
     * @param array $data Person details including name and email
     * @return array Created person data from MDP
     * @throws ValidationException If person data is invalid
     * @throws ApiException If person creation fails
     */
    private function createPerson(array $data): array;
}
```

## UI Components

### Bulk Upload Modal
```php
[
    'modal' => [
        'id' => 'bulk-upload-modal',
        'title' => 'Bulk Add Members',
        'trigger_button' => [
            'text' => 'Bulk Add Members',
            'classes' => 'button button-secondary',
            'attributes' => [
                'hx-get' => '/wp-html/organization/{uuid}/members/bulk-add/modal',
                'hx-target' => '#modal-container'
            ]
        ],
        'content' => [
            'csv_format' => [
                'title' => 'Required CSV Format',
                'columns' => [
                    'first_name' => ['required' => true, 'description' => 'Member\'s first name'],
                    'last_name' => ['required' => true, 'description' => 'Member\'s last name'],
                    'email' => ['required' => true, 'description' => 'Valid email address'],
                    'roles' => ['required' => false, 'description' => 'Pipe separated roles (e.g., member|org_editor)']
                ],
                'example' => 'first_name,last_name,email,roles
John,Doe,john@example.com,member|org_editor
Jane,Smith,jane@example.com,member'
            ],
            'sample_file' => [
                'text' => 'Download Sample CSV',
                'filename' => 'member-upload-template.csv',
                'url' => '/wp-html/organization/{uuid}/members/download-template'
            ]
        ]
    ]
]
```



## Error Handling
- Email already exists in organization
- Invalid email format
- Required fields missing
- Invalid role assignments
- CSV format errors
- File upload errors
- MDP API communication errors

## CSV Processing
- Header validation
- Row validation
- Data sanitization
- Batch processing
- Progress feedback
- Error reporting
