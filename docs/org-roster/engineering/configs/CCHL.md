# CCHL Configuration

Source of truth: `../cchl-website-wordpress/src/web/app/themes/industrial/custom/org-roster.php`

This document mirrors the current site override. If it drifts, update the site config first, then update this file.

## Active Strategy

- `membership.strategy = direct`

## Current Override Paths

### `access`

- `access.roles.owner = membership_owner`
- `access.roles.manager = membership_manager`
- `access.roles.editor = org_editor`
- `access.roles.labels.membership_manager = Membership Manager`
- `access.roles.labels.org_editor = Org Editor`
- `access.roles.labels.membership_owner = Membership Owner`
- `access.roles.labels.Cchlmembercommunity = CCHL Member Community`
- `access.roles.labels.cchlmembercommunity = CCHL Member Community`
- `access.permissions.organization_edit_roles = ['org_editor']`
- `access.permissions.manage_member_roles = ['membership_manager', 'membership_owner']`
- `access.permissions.add_member_roles = ['membership_manager', 'membership_owner']`
- `access.permissions.remove_member_roles = ['membership_manager', 'membership_owner']`
- `access.permissions.purchase_seat_roles = ['membership_owner', 'membership_manager', 'org_editor']`
- `access.permissions.any_management_roles = ['org_editor', 'membership_manager', 'membership_owner']`
- `access.permissions.prevent_owner_removal = false`
- `access.permissions.prevent_owner_assignment = true`
- `access.permissions.relationship_grants.enabled = false`
- `access.permissions.relationship_grants.roles_by_type.ceo = ['org_editor', 'membership_manager']`
- `access.permissions.relationship_grants.roles_by_type.primary_hr_contact = ['org_editor', 'membership_manager']`
- `access.permissions.relationship_grants.roles_by_type.member_contact = ['org_editor', 'membership_manager']`
- `access.permissions.relationship_grants.roles_by_type.employee = []`
- `access.permissions.relationship_grants.roles_by_type.advertising_sponsor_contact = []`
- `access.permissions.relationship_grants.roles_by_type.advertising_sponsor_billing = []`

### `membership`

- `membership.strategy = direct`

### `relationships`

- `relationships.defaults.type = Position`
- `relationships.addition.type = position`
- `relationships.filters.allowlist = []`
- `relationships.filters.denylist = []`
- `relationships.labels.custom.ceo = CEO`
- `relationships.labels.custom.primary_hr_contact = Primary HR Contact`
- `relationships.labels.custom.employee = Employee`
- `relationships.labels.custom.member_contact = Member Contact`
- `relationships.labels.special.advertising_sponsor_contact = Advertising/Sponsor Contact`
- `relationships.labels.special.advertising_sponsor_billing = Advertising/Sponsor Billing Contact`

### `member_management`

- `member_management.addition.auto_assign_roles = ['supplemental_member', 'CCHL Member Community']`
- `member_management.addition.base_member_role = member`
- `member_management.addition.auto_opt_in_communications.enabled = true`
- `member_management.addition.auto_opt_in_communications.email = true`
- `member_management.addition.auto_opt_in_communications.sublists = ['one', 'two', 'three', 'four', 'five']`
- `member_management.forms.add_member.layout = full`
- `member_management.forms.add_member.fields.first_name.enabled = true`
- `member_management.forms.add_member.fields.first_name.required = true`
- `member_management.forms.add_member.fields.first_name.label = First Name`
- `member_management.forms.add_member.fields.last_name.enabled = true`
- `member_management.forms.add_member.fields.last_name.required = true`
- `member_management.forms.add_member.fields.last_name.label = Last Name`
- `member_management.forms.add_member.fields.email.enabled = true`
- `member_management.forms.add_member.fields.email.required = true`
- `member_management.forms.add_member.fields.email.label = Email Address`
- `member_management.forms.add_member.fields.relationship_type.enabled = false`
- `member_management.forms.add_member.fields.relationship_type.required = false`
- `member_management.forms.add_member.fields.relationship_type.label = Relationship Type`
- `member_management.forms.add_member.fields.permissions.enabled = true`
- `member_management.forms.add_member.fields.permissions.required = true`
- `member_management.forms.add_member.fields.permissions.label = Permissions`
- `member_management.forms.add_member.fields.permissions.allowlist = []`
- `member_management.forms.add_member.fields.permissions.denylist = ['Cchlmembercommunity', 'cchlmembercommunity']`
- `member_management.forms.add_member.allow_relationship_type_editing = false`
- `member_management.permissions_modal.allowlist = []`
- `member_management.permissions_modal.denylist = ['Cchlmembercommunity', 'cchlmembercommunity']`

### `presentation`

- `presentation.relationships.show_type = false`
- `presentation.relationships.show_special_types = false`
- `presentation.member_card.fields.name.enabled = true`
- `presentation.member_card.fields.name.label = Name`
- `presentation.member_card.fields.email.enabled = true`
- `presentation.member_card.fields.email.label = Email`
- `presentation.member_card.fields.roles.enabled = true`
- `presentation.member_card.fields.roles.label = Roles`
- `presentation.member_card.fields.relationship_type.enabled = false`
- `presentation.member_card.fields.relationship_type.label = Relationship`

### `integrations`

- `integrations.additional_seats.enabled = true`
- `integrations.additional_seats.sku = additional-seats`
- `integrations.additional_seats.discount_sku = corporate-seat-discount`
- `integrations.additional_seats.form_id = 0`
- `integrations.additional_seats.form_slug = additional-seats`
- `integrations.additional_seats.min_quantity = 1`
- `integrations.additional_seats.max_quantity = 900`
- `integrations.documents.allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif']`
- `integrations.documents.max_size = 10485760`
- `integrations.business_info.seat_limit_info = null`
- `integrations.notifications.confirmation_email_from = cchl@wicketcloud.com`

### `platform`

- `platform.cache.enabled = false`
- `platform.cache.duration = 300`

## Current Config Function

```php
function wicket_orgman_config(array $config): array
{
    $config['access']['roles'] = [
        'owner' => 'membership_owner',
        'manager' => 'membership_manager',
        'editor' => 'org_editor',
        'labels' => [
            'membership_manager' => 'Membership Manager',
            'org_editor' => 'Org Editor',
            'membership_owner' => 'Membership Owner',
            'Cchlmembercommunity' => 'CCHL Member Community',
            'cchlmembercommunity' => 'CCHL Member Community',
        ],
    ];

    $config['access']['permissions']['organization_edit_roles'] = ['org_editor'];
    $config['access']['permissions']['manage_member_roles'] = ['membership_manager', 'membership_owner'];
    $config['access']['permissions']['add_member_roles'] = ['membership_manager', 'membership_owner'];
    $config['access']['permissions']['remove_member_roles'] = ['membership_manager', 'membership_owner'];
    $config['access']['permissions']['purchase_seat_roles'] = ['membership_owner', 'membership_manager', 'org_editor'];
    $config['access']['permissions']['any_management_roles'] = ['org_editor', 'membership_manager', 'membership_owner'];
    $config['access']['permissions']['prevent_owner_removal'] = false;
    $config['access']['permissions']['prevent_owner_assignment'] = true;
    $config['access']['permissions']['relationship_grants']['enabled'] = false;
    $config['access']['permissions']['relationship_grants']['roles_by_type'] = [
        'ceo' => ['org_editor', 'membership_manager'],
        'primary_hr_contact' => ['org_editor', 'membership_manager'],
        'member_contact' => ['org_editor', 'membership_manager'],
        'employee' => [],
        'advertising_sponsor_contact' => [],
        'advertising_sponsor_billing' => [],
    ];

    // CCHL-only roles: keep explicit because shared library defaults are intentionally neutral.
    $config['member_management']['addition']['auto_assign_roles'] = [
        'supplemental_member',
        'CCHL Member Community',
    ];
    $config['member_management']['addition']['base_member_role'] = 'member';
    $config['member_management']['addition']['auto_opt_in_communications'] = [
        'enabled' => true,
        'email' => true,
        'sublists' => ['one', 'two', 'three', 'four', 'five'],
    ];

    $config['platform']['cache']['enabled'] = false;
    $config['platform']['cache']['duration'] = 5 * 60;

    $config['relationships']['defaults']['type'] = 'Position';
    $config['relationships']['addition']['type'] = 'position';
    $config['relationships']['filters']['allowlist'] = [];
    $config['relationships']['filters']['denylist'] = [];

    $config['membership']['strategy'] = 'direct';

    $config['integrations']['additional_seats']['enabled'] = true;
    $config['integrations']['additional_seats']['sku'] = 'additional-seats';
    $config['integrations']['additional_seats']['discount_sku'] = 'corporate-seat-discount';
    $config['integrations']['additional_seats']['form_id'] = 0;
    $config['integrations']['additional_seats']['form_slug'] = 'additional-seats';
    $config['integrations']['additional_seats']['min_quantity'] = 1;
    $config['integrations']['additional_seats']['max_quantity'] = 900;

    $config['integrations']['documents']['allowed_types'] = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif',
    ];
    $config['integrations']['documents']['max_size'] = 10 * 1024 * 1024;
    $config['integrations']['business_info']['seat_limit_info'] = null;

    $config['presentation']['relationships']['show_type'] = false;
    $config['presentation']['relationships']['show_special_types'] = false;
    $config['presentation']['member_card']['fields'] = [
        'name' => ['enabled' => true, 'label' => 'Name'],
        'email' => ['enabled' => true, 'label' => 'Email'],
        'roles' => ['enabled' => true, 'label' => 'Roles'],
        'relationship_type' => ['enabled' => false, 'label' => 'Relationship'],
    ];

    $config['member_management']['forms']['add_member']['layout'] = 'full';
    $config['member_management']['forms']['add_member']['fields']['first_name'] = [
        'enabled' => true,
        'required' => true,
        'label' => __('First Name', 'wicket-acc'),
    ];
    $config['member_management']['forms']['add_member']['fields']['last_name'] = [
        'enabled' => true,
        'required' => true,
        'label' => __('Last Name', 'wicket-acc'),
    ];
    $config['member_management']['forms']['add_member']['fields']['email'] = [
        'enabled' => true,
        'required' => true,
        'label' => __('Email Address', 'wicket-acc'),
    ];
    $config['member_management']['forms']['add_member']['fields']['relationship_type'] = [
        'enabled' => false,
        'required' => false,
        'label' => __('Relationship Type', 'wicket-acc'),
    ];
    $config['member_management']['forms']['add_member']['fields']['permissions'] = [
        'enabled' => true,
        'required' => true,
        'label' => __('Permissions', 'wicket-acc'),
        'allowlist' => [],
        'denylist' => ['Cchlmembercommunity', 'cchlmembercommunity'],
    ];
    $config['member_management']['forms']['add_member']['allow_relationship_type_editing'] = false;

    $config['member_management']['permissions_modal']['allowlist'] = [];
    $config['member_management']['permissions_modal']['denylist'] = ['Cchlmembercommunity', 'cchlmembercommunity'];

    $config['integrations']['notifications']['confirmation_email_from'] = 'cchl@wicketcloud.com';

    $config['relationships']['labels']['custom'] = [
        'ceo' => 'CEO',
        'primary_hr_contact' => 'Primary HR Contact',
        'employee' => 'Employee',
        'member_contact' => 'Member Contact',
    ];
    $config['relationships']['labels']['special'] = [
        'advertising_sponsor_contact' => 'Advertising/Sponsor Contact',
        'advertising_sponsor_billing' => 'Advertising/Sponsor Billing Contact',
    ];

    return $config;
}
```
