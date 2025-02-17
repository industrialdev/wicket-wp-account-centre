# Account Centre Options

## Organization Management Configuration

### Global Settings

#### MDP Affiliation Mode
This setting determines how organization affiliations are handled through the MDP system.

```php
[
    'mdp_affiliation_mode' => [
        'type' => 'select',
        'required' => true,
        'label' => 'MDP Affiliation Mode',
        'description' => 'Select how organization affiliations will be managed through MDP. This setting affects all organization-related blocks.',
        'options' => [
            'direct' => [
                'label' => 'Direct Affiliation',
                'description' => 'Users are directly affiliated with organizations. Simplest approach for basic organization management.'
            ],
            'cascade' => [
                'label' => 'Cascade Affiliation',
                'description' => 'Users are connected through relationship mapping. Best for complex organizational structures.'
            ],
            'group' => [
                'label' => 'Group Affiliation',
                'description' => 'Users are managed through groups. Ideal for hierarchical organization structures.'
            ]
        ],
        'default' => 'direct',
        'warning_on_change' => 'Changing this setting will affect how all existing organization affiliations are handled. Existing connections may need to be reconfigured.'
    ]
]
```

### Group Management Settings
Only applicable when `mdpAffiliationMode` is set to `group`.

```php
[
    'group_management' => [
        'type' => 'fieldset',
        'depends_on' => [
            'field' => 'mdp_affiliation_mode',
            'value' => 'group'
        ],
        'fields' => [
            'allow_parent_groups' => [
                'type' => 'boolean',
                'label' => 'Allow Parent Groups',
                'description' => 'Enable parent group associations',
                'default' => true
            ],
            'allow_child_groups' => [
                'type' => 'boolean',
                'label' => 'Allow Child Groups',
                'description' => 'Enable child group associations',
                'default' => true
            ],
            'max_group_depth' => [
                'type' => 'number',
                'label' => 'Maximum Group Hierarchy Depth',
                'description' => 'Maximum levels of group nesting allowed',
                'min' => 1,
                'max' => 10,
                'default' => 3
            ]
        ]
    ]
]
```

## Implementation Details

### Configuration Storage
- Settings stored in WordPress options table
- Key: `wicket_acc_options`
- Serialized array of all ACC settings

### Integration Methods
```php
class OrganizationManager {
    /**
     * Get the globally configured MDP affiliation mode
     * Returns the current affiliation mode from WordPress options
     * Default to 'direct' if not configured
     *
     * @return string The MDP affiliation mode ('direct', 'cascade', or 'group')
     * @throws ConfigurationException If options are corrupted
     */
    public static function getMdpAffiliationMode(): string;

    /**
     * Get group management settings if applicable
     * Only returns settings when mdpAffiliationMode is 'group'
     * Includes parent/child group settings and hierarchy depth
     *
     * @return array|null Group management settings or null if not in group mode
     * @throws ConfigurationException If group settings are invalid
     */
    public static function getGroupManagementSettings(): ?array;

    /**
     * Validate affiliation mode change
     * Checks if changing affiliation mode is safe
     * Returns list of potential issues
     *
     * @param string $newMode New affiliation mode to validate
     * @return array List of warnings or potential issues
     * @throws ValidationException If new type is invalid
     */
    public static function validateAffiliationModeChange(string $newMode): array;
}
```

### Migration Considerations
When changing the MDP affiliation mode:
1. Display warning about potential impact
2. Provide migration guidance
3. Consider adding migration tools for different scenarios:
   - Direct → Cascade
   - Direct → Group
   - Cascade → Direct
   - Cascade → Group
   - Group → Direct
   - Group → Cascade
