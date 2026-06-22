# NJBIA Configuration

Source of truth: `../njbia-website-wordpress/src/wp-content/themes/njbia/theme/inc/org-roster.php`

This document mirrors the current site override. If it drifts, update the site config first, then update this file.

## Active Strategy

- `membership.strategy = cascade`

## Current Override Paths

### `membership`

- `membership.strategy = cascade`

### `relationships`

- `relationships.defaults.type = member_contact`
- `relationships.filters.allowlist = []`
- `relationships.filters.denylist = []`
- `relationships.labels.custom.employee_staff = Employee`

### `member_management`

- `member_management.forms.add_member.layout = simplified`
- `member_management.forms.add_member.fields.first_name.enabled = true`
- `member_management.forms.add_member.fields.last_name.enabled = true`
- `member_management.forms.add_member.fields.email.enabled = true`
- `member_management.forms.add_member.fields.relationship_type.enabled = true`
- `member_management.forms.add_member.fields.description.enabled = true`
- `member_management.forms.add_member.fields.description.label = Job Title`
- `member_management.forms.add_member.fields.description.input_type = text`
- `member_management.forms.add_member.allow_relationship_type_editing = true`
- `member_management.bulk_upload.columns.first_name.enabled = true`
- `member_management.bulk_upload.columns.first_name.required = true`
- `member_management.bulk_upload.columns.first_name.header = First Name`
- `member_management.bulk_upload.columns.first_name.aliases = ['first name', 'firstname', 'first']`
- `member_management.bulk_upload.columns.last_name.enabled = true`
- `member_management.bulk_upload.columns.last_name.required = true`
- `member_management.bulk_upload.columns.last_name.header = Last Name`
- `member_management.bulk_upload.columns.last_name.aliases = ['last name', 'lastname', 'last']`
- `member_management.bulk_upload.columns.email.enabled = true`
- `member_management.bulk_upload.columns.email.required = true`
- `member_management.bulk_upload.columns.email.header = Email Address`
- `member_management.bulk_upload.columns.email.aliases = ['email address', 'email', 'e-mail']`
- `member_management.bulk_upload.columns.relationship_type.enabled = true`
- `member_management.bulk_upload.columns.relationship_type.required = true`
- `member_management.bulk_upload.columns.relationship_type.header = Relationship Type`
- `member_management.bulk_upload.columns.relationship_type.aliases = ['relationship type', 'relationship']`
- `member_management.bulk_upload.columns.roles.enabled = true`
- `member_management.bulk_upload.columns.roles.required = false`
- `member_management.bulk_upload.columns.roles.header = Roles`
- `member_management.bulk_upload.columns.roles.aliases = ['roles', 'permissions', 'role']`
- `member_management.bulk_upload.relationship_type.required = true`
- `member_management.bulk_upload.relationship_type.allowed_types = ['employee_staff']`
- `member_management.bulk_upload.relationship_type.aliases.employee = employee_staff`

### `access`

- `access.permissions.add_member_roles = ['membership_manager']`
- `access.permissions.prevent_owner_removal = true`
- `access.permissions.remove_member_roles = ['membership_manager']`
- `access.permissions.manage_member_roles = ['membership_manager']`
- `access.permissions.relationship_grants.enabled = true`
- `access.permissions.relationship_grants.roles_by_type.ceo = ['org_editor', 'membership_manager']`
- `access.permissions.relationship_grants.roles_by_type.primary_hr_contact = ['org_editor', 'membership_manager']`
- `access.permissions.relationship_grants.roles_by_type.member_contact = ['org_editor', 'membership_manager']`
- `access.permissions.relationship_grants.roles_by_type.employee = []`
- `access.permissions.relationship_grants.roles_by_type.advertising_sponsor_contact = []`
- `access.permissions.relationship_grants.roles_by_type.advertising_sponsor_billing = []`

### `presentation`

- `presentation.relationships.show_special_types = true`
- `presentation.relationships.show_type = true`
- `presentation.member_card.fields.relationship_type.enabled = true`
- `presentation.member_card.fields.job_title.enabled = false`
- `presentation.member_card.fields.job_title.label = Job Title`
- `presentation.member_card.fields.description.enabled = true`
- `presentation.member_list.show_bulk_upload = true`
- `ui.member_list.show_bulk_upload = true`

### `integrations.additional_seats`

- `integrations.additional_seats.enabled = true`
- `integrations.additional_seats.sku = additional-seats`
- `integrations.additional_seats.discount_sku = corporate-seat-discount`
- `integrations.additional_seats.form_id = 0`
- `integrations.additional_seats.form_slug = additional-seats`
- `integrations.additional_seats.min_quantity = 1`
- `integrations.additional_seats.max_quantity = 900`

## Current Config Function

```php
function njbia_orgman_config(array $config): array
{
    $config['membership']['strategy'] = 'cascade';
    $config['relationships']['defaults']['type'] = 'member_contact';
    $config['relationships']['filters']['allowlist'] = [];
    $config['relationships']['filters']['denylist'] = [];
    $config['relationships']['labels']['custom']['employee_staff'] = __('Employee', 'wicket-acc');

    // Enable simplified member addition form with custom fields
    $config['member_management']['forms']['add_member']['layout'] = 'simplified';
    $config['member_management']['forms']['add_member']['fields']['first_name']['enabled'] = true;
    $config['member_management']['forms']['add_member']['fields']['last_name']['enabled'] = true;
    $config['member_management']['forms']['add_member']['fields']['email']['enabled'] = true;
    $config['member_management']['forms']['add_member']['fields']['relationship_type']['enabled'] = true;
    $config['member_management']['forms']['add_member']['fields']['description']['enabled'] = true;
    $config['member_management']['forms']['add_member']['fields']['description']['label'] = __('Job Title', 'wicket-acc');
    $config['member_management']['forms']['add_member']['fields']['description']['input_type'] = 'text';

    // Restrict member management to Membership Managers only (not owners)
    $config['access']['permissions']['add_member_roles'] = ['membership_manager'];
    $config['access']['permissions']['prevent_owner_removal'] = true;
    $config['access']['permissions']['remove_member_roles'] = ['membership_manager'];
    $config['access']['permissions']['manage_member_roles'] = ['membership_manager'];

    // Show special relationship types on member cards
    $config['presentation']['relationships']['show_special_types'] = true;
    $config['presentation']['member_card']['fields']['relationship_type']['enabled'] = true;
    $config['presentation']['member_card']['fields']['job_title']['enabled'] = false;
    $config['presentation']['member_card']['fields']['job_title']['label'] = __('Job Title', 'wicket-acc');
    $config['presentation']['member_card']['fields']['description']['enabled'] = true;
    $config['presentation']['relationships']['show_type'] = true;
    // Keep both flags enabled for backward compatibility across lib config schemas.
    $config['presentation']['member_list']['show_bulk_upload'] = true;
    $config['ui']['member_list']['show_bulk_upload'] = true;

    // Bulk upload: keep all imported columns enabled for this site.
    $config['member_management']['bulk_upload']['columns'] = [
        'first_name' => [
            'enabled' => true,
            'required' => true,
            'header' => __('First Name', 'wicket-acc'),
            'aliases' => ['first name', 'firstname', 'first'],
        ],
        'last_name' => [
            'enabled' => true,
            'required' => true,
            'header' => __('Last Name', 'wicket-acc'),
            'aliases' => ['last name', 'lastname', 'last'],
        ],
        'email' => [
            'enabled' => true,
            'required' => true,
            'header' => __('Email Address', 'wicket-acc'),
            'aliases' => ['email address', 'email', 'e-mail'],
        ],
        'relationship_type' => [
            'enabled' => true,
            'required' => true,
            'header' => __('Relationship Type', 'wicket-acc'),
            'aliases' => ['relationship type', 'relationship'],
        ],
        'roles' => [
            'enabled' => true,
            'required' => false,
            'header' => __('Roles', 'wicket-acc'),
            'aliases' => ['roles', 'permissions', 'role'],
        ],
    ];
    $config['member_management']['bulk_upload']['relationship_type'] = [
        'required' => true,
        'allowed_types' => ['employee_staff'],
        'aliases' => [
            'employee' => 'employee_staff',
        ],
    ];

    // Enable relationship-based permissions
    $config['access']['permissions']['relationship_grants']['enabled'] = true;
    $config['access']['permissions']['relationship_grants']['roles_by_type'] = [
        'ceo' => ['org_editor', 'membership_manager'],
        'primary_hr_contact' => ['org_editor', 'membership_manager'],
        'member_contact' => ['org_editor', 'membership_manager'],
        'employee' => [],
        'advertising_sponsor_contact' => [],
        'advertising_sponsor_billing' => [],
    ];

    // Enable relationship type editing
    $config['member_management']['forms']['add_member']['allow_relationship_type_editing'] = true;
    $config['integrations']['additional_seats']['enabled'] = true;
    $config['integrations']['additional_seats']['sku'] = 'additional-seats';
    $config['integrations']['additional_seats']['discount_sku'] = 'corporate-seat-discount';
    $config['integrations']['additional_seats']['form_id'] = 0;
    $config['integrations']['additional_seats']['form_slug'] = 'additional-seats';
    $config['integrations']['additional_seats']['min_quantity'] = 1;
    $config['integrations']['additional_seats']['max_quantity'] = 900;

    return $config;
}
```
