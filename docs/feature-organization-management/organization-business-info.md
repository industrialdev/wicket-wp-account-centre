# Organization Business Information Block

## Overview
Provides configurable editing interface for organization's additional information sections from MDP. Block configuration allows enabling/disabling specific information sub-blocks based on client requirements.

## Block Identification
- **Slug**: `wicket-ac/organization-business-info`
- **Name**: Organization Business Information

## Access Control

### Required Roles
Users must have at least one of these roles to access the block:
- administrator
- membership_owner
- org_editor

## Block Architecture

### Configuration Panel
```php
[
    'block_settings' => [
        'available_sections' => [
            'type' => 'dynamic-checkboxes',
            'label' => 'Available Information Sections',
            'description' => 'Select which information sections to display',
            'source' => 'getMDPAvailableSections()',
            'default' => 'all'
        ],
        'section_ordering' => [
            'type' => 'sortable-list',
            'label' => 'Section Display Order',
            'depends_on' => 'available_sections'
        ]
    ]
]
```

### Date Control Settings
```php
[
    'date_control' => [
        'enabled' => [
            'type' => 'boolean',
            'default' => false,
            'label' => 'Enable Date Control'
        ],
        'start_date' => [
            'type' => 'date',
            'required' => false,
            'label' => 'Start Date',
            'depends_on' => 'date_control.enabled'
        ],
        'end_date' => [
            'type' => 'date',
            'required' => false,
            'label' => 'End Date',
            'depends_on' => 'date_control.enabled'
        ],
        'info_message' => [
            'type' => 'rich_text',
            'required' => false,
            'label' => 'Message to display outside date range',
            'default' => 'This information is not available at this time.',
            'depends_on' => 'date_control.enabled'
        ]
    ]
]
```

### Data Structure
```php
[
    'sections' => [
        'section_id' => [
            'title' => 'string',
            'enabled' => 'boolean',
            'order' => 'int',
            'fields' => [
                'field_id' => [
                    'type' => 'string',      // text|select|checkbox|etc
                    'label' => 'string',
                    'required' => 'boolean',
                    'validation' => 'array',
                    'options' => 'array|null' // For select/radio fields
                ]
            ]
        ]
    ],
    'metadata' => [
        'last_updated' => 'string',
        'updated_by' => [
            'uuid' => 'string',
            'name' => 'string'
        ]
    ]
]
```

## Integration with MDP

### Main Methods
```php
class OrganizationBusinessInfo {
    /**
     * Retrieves all available sections from MDP for a given organization
     * Sections are filtered based on organization configuration
     *
     * @param string $organizationUuid Organization identifier
     * @return array List of available sections with their metadata
     * @throws OrganizationNotFoundException If organization not found
     */
    public function getAvailableSections(string $organizationUuid): array;

    /**
     * Retrieves data for a specific section of organization's business info
     * Includes field values and validation rules
     *
     * @param string $organizationUuid Organization identifier
     * @param string $sectionId Section identifier
     * @return array Section data with field values
     * @throws SectionNotFoundException If section not found or disabled
     */
    public function getSectionData(
        string $organizationUuid,
        string $sectionId
    ): array;

    /**
     * Updates data for a specific section in MDP
     * Validates data before sending update
     *
     * @param string $organizationUuid Organization identifier
     * @param string $sectionId Section identifier
     * @param array $data New section data
     * @return bool Success status
     * @throws ValidationException If data fails validation
     */
    public function updateSectionData(
        string $organizationUuid,
        string $sectionId,
        array $data
    ): bool;

    /**
     * Retrieves configuration of enabled sections
     * Used internally to filter available sections
     *
     * @return array Enabled sections configuration
     */
    private function getEnabledSections(): array;
}
```

### Visibility Control
```php
class BusinessInfoVisibility {
    /**
     * Determines if block should be visible based on configured date range
     * If date control is disabled, returns true
     * If both dates are empty, returns true
     * Otherwise checks if current date is within range
     *
     * @return bool True if block should be visible
     */
    public function isBlockVisible(): bool;

    /**
     * Returns configured message for when block is not visible
     * Message is sanitized using wp_kses_post
     *
     * @return string Sanitized message
     */
    public function getInfoMessage(): string;
}
```

### Integration into Main Block Flow
```php
class OrganizationBusinessInfo {
    private $visibility;

    public function __construct() {
        $this->visibility = new BusinessInfoVisibility();
    }

    public function render(): string {
        if (!$this->visibility->isBlockVisible()) {
            return $this->renderInfoMessage();
        }

        return $this->renderBusinessInfo();
    }

    private function renderInfoMessage(): string {
        return sprintf(
            '<div class="business-info-message">%s</div>',
            $this->visibility->getInfoMessage()
        );
    }
}
```

### Section Update Flow
```php
/**
 * Pseudo-code for section update process
 */
public function updateSectionData(
    string $organizationUuid,
    string $sectionId,
    array $data
): bool {
}
```

## Required Legacy Functions

## Error Handling
- Missing required fields
- Invalid field values
- Section not enabled
- Permission denied
- API communication errors

## Validation Rules
1. All enabled sections must have valid configurations
2. Required fields must be populated
3. Field values must match expected formats
4. Changes must be authorized
5. Section updates must be atomic

## State Management
- Track unsaved changes
- Cache section data
- Maintain edit history
- Handle concurrent edits
- Clear cache on updates

## Event Hooks
```php
[
    'filters' => [
        'wicket_business_info_sections' => [
            'description' => 'Filter available business info sections',
            'arguments' => ['sections', 'organization_uuid']
        ],
        'wicket_business_info_fields' => [
            'description' => 'Filter fields within a section',
            'arguments' => ['fields', 'section_id', 'organization_uuid']
        ]
    ],
    'actions' => [
        'wicket_before_business_info_update' => [
            'description' => 'Before section update',
            'arguments' => ['section_id', 'data', 'organization_uuid']
        ],
        'wicket_after_business_info_update' => [
            'description' => 'After successful update',
            'arguments' => ['section_id', 'data', 'organization_uuid']
        ]
    ]
]
```

## Sub-block Structure

### Available Sub-blocks
```php
[
    'operational_status' => [
        'title' => 'Operational Status',
        'endpoint' => '/wp-html/organization/{uuid}/business-info/operational-status',
        'fields' => [
            'status' => [
                'type' => 'select',
                'required' => true,
                'options' => ['active', 'inactive', 'seasonal']
            ],
            'year_established' => [
                'type' => 'number',
                'required' => false,
                'min' => 1800,
                'max' => 'current_year'
            ],
            'number_of_employees' => [
                'type' => 'select',
                'required' => true,
                'options' => ['1-10', '11-50', '51-200', '201-500', '500+']
            ]
        ]
    ],
    'retail_presence' => [
        'title' => 'Retail Presence',
        'endpoint' => '/wp-html/organization/{uuid}/business-info/retail-presence',
        'fields' => [
            'has_physical_locations' => [
                'type' => 'boolean',
                'required' => true
            ],
            'location_count' => [
                'type' => 'number',
                'required' => false,
                'depends_on' => 'has_physical_locations'
            ],
            'presence_type' => [
                'type' => 'checkboxes',
                'options' => ['brick_and_mortar', 'online', 'mobile']
            ]
        ]
    ],
    'revenue' => [
        'title' => 'Revenue Information',
        'endpoint' => '/wp-html/organization/{uuid}/business-info/revenue',
        'fields' => [
            'range' => [
                'type' => 'select',
                'required' => true,
                'options' => ['0-100k', '100k-500k', '500k-1m', '1m-5m', '5m+']
            ],
            'currency' => [
                'type' => 'select',
                'required' => true,
                'options' => 'getCurrencies()'
            ]
        ]
    ],
    'business_categories' => [
        'title' => 'Business Categories',
        'sub_forms' => [
            'company_attributes' => [
                'title' => 'Company Attributes',
                'endpoint' => '/wp-html/organization/{uuid}/business-info/attributes',
                'fields' => [
                    'business_type' => [
                        'type' => 'select',
                        'required' => true,
                        'options' => 'getBusinessTypes()'
                    ],
                    'ownership_type' => [
                        'type' => 'select',
                        'required' => true,
                        'options' => 'getOwnershipTypes()'
                    ]
                ]
            ],
            'certifications' => [
                'title' => 'Certifications',
                'endpoint' => '/wp-html/organization/{uuid}/business-info/certifications',
                'fields' => [
                    'certifications' => [
                        'type' => 'repeater',
                        'fields' => [
                            'name' => ['type' => 'text', 'required' => true],
                            'issuer' => ['type' => 'text', 'required' => true],
                            'expiry' => ['type' => 'date', 'required' => false]
                        ]
                    ]
                ]
            ],
            'business_services' => [
                'title' => 'Business Services',
                'endpoint' => '/wp-html/organization/{uuid}/business-info/services',
                'fields' => [
                    'services' => [
                        'type' => 'checkboxes',
                        'options' => 'getBusinessServices()',
                        'allow_multiple' => true
                    ]
                ]
            ],
            'interests' => [
                'title' => 'Business Interests',
                'endpoint' => '/wp-html/organization/{uuid}/business-info/interests',
                'fields' => [
                    'interests' => [
                        'type' => 'tags',
                        'suggestions' => 'getInterestSuggestions()',
                        'allow_custom' => true
                    ]
                ]
            ]
        ]
    ]
]
```

### Form Integration
```php
class BusinessInfoForm {
    /**
     * Saves data for a specific sub-block or its sub-form
     * Handles validation, permissions, and API communication
     * Supports both main blocks and nested sub-forms
     * Triggers appropriate hooks before and after save
     *
     * @param string $organizationUuid Organization identifier
     * @param string $blockId Block being updated (e.g., 'operational_status')
     * @param string|null $subFormId Optional sub-form identifier
     * @param array $data Form data to be saved
     * @return bool Success status of the save operation
     * @throws UnauthorizedException If user lacks required permissions
     * @throws ValidationException If data fails validation
     * @throws ApiException If MDP communication fails
     */
    public function saveSubBlockData(
        string $organizationUuid,
        string $blockId,
        ?string $subFormId,
        array $data
    ): bool;

    /**
     * Validates all required fields for a given form
     * Checks field types, formats, and dependencies
     *
     * @param array $data Form data to validate
     * @param array $schema Form field schema
     * @return bool True if validation passes
     * @throws ValidationException With detailed error messages
     */
    private function validateFormData(array $data, array $schema): bool;

    /**
     * Formats form data for API submission
     * Converts from form structure to API expected format
     *
     * @param array $data Raw form data
     * @param string $blockId Block identifier
     * @return array Formatted data for API
     */
    private function formatDataForApi(array $data, string $blockId): array;
}
```

### BusinessInfoVisibility
```php
class BusinessInfoVisibility {
    /**
     * Checks if block should be shown based on date range settings
     * Considers empty dates as always visible
     * Validates current date against configured range
     *
     * @return bool True if block should be visible
     */
    public function isBlockVisible(): bool;

    /**
     * Gets configured message for when block is hidden
     * Returns default message if none configured
     * Sanitizes output for security
     *
     * @return string Sanitized message to display
     */
    public function getInfoMessage(): string;
}
```

### Section Management
```php
class SectionManager {
    /**
     * Gets available sections from MDP configuration
     * Filters based on client settings and permissions
     * Sorts sections according to configured order
     *
     * @param string $organizationUuid Organization identifier
     * @return array List of available sections with metadata
     * @throws ConfigurationException If section config is invalid
     */
    public function getAvailableSections(string $organizationUuid): array;

    /**
     * Retrieves data for a specific section
     * Includes field values and validation rules
     * Caches results for performance
     *
     * @param string $organizationUuid Organization identifier
     * @param string $sectionId Section to retrieve
     * @return array Section data and field values
     * @throws SectionNotFoundException If section not found
     */
    public function getSectionData(
        string $organizationUuid,
        string $sectionId
    ): array;

    /**
     * Updates section data in MDP
     * Validates data against section schema
     * Handles nested field structures
     *
     * @param string $organizationUuid Organization identifier
     * @param string $sectionId Section to update
     * @param array $data New section data
     * @return bool Success status
     * @throws ValidationException If data invalid
     * @throws ApiException If update fails
     */
    public function updateSectionData(
        string $organizationUuid,
        string $sectionId,
        array $data
    ): bool;

    /**
     * Gets list of enabled sections and their order
     * Uses block configuration and client settings
     *
     * @return array Enabled sections with order info
     */
    private function getEnabledSections(): array;

    /**
     * Retrieves MDP field schema for a specific section
     * Caches schema for performance
     * Supports lookup by slug, UUID, or schema identifier
     * Includes field types, validation rules, and dependencies
     *
     * @param string $identifier Section identifier (slug|uuid|schema)
     * @param string $identifierType Type of identifier being used
     * @return array Field schema with validation rules
     * @throws SchemaNotFoundException If schema not found
     * @throws InvalidIdentifierException If identifier type invalid
     */
    public function getFieldSchema(
        string $identifier,
        string $identifierType = 'slug'
    ): array {
        // Method will be implemented to:
        // 1. Validate identifier type (slug|uuid|schema)
        // 2. Query MDP for schema definition
        // 3. Process and cache schema data
        // 4. Return formatted schema with all field definitions
    }
}
```

## Additional Legacy Functions
- `wicket_orgman_get_business_types()`
- `wicket_orgman_get_ownership_types()`
- `wicket_orgman_get_business_services()`
- `wicket_orgman_get_interest_suggestions()`

## UI/UX Requirements
1. Each sub-block displays as a collapsible section
2. Sub-forms appear within their parent block
3. Individual save buttons per form
4. Real-time validation per field
5. Success/error feedback per form submission
6. Proper loading states during saves
