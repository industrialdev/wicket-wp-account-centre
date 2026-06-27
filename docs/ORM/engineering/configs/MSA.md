# MSA Configuration

Source of truth: `../msa-website-wordpress/src/web/app/themes/wicket-child/custom/org-roster.php`

This document mirrors the current site override. If it drifts, update the site config first, then update this file.

## Active Strategy

- `membership.strategy = cascade`

## Current Override Paths

### `access`

- `access.permissions.manage_member_roles = ['membership_manager', 'membership_owner']`
- `access.permissions.add_member_roles = ['membership_manager', 'membership_owner']`
- `access.permissions.remove_member_roles = []`
- `access.permissions.role_only_management_access.enabled = true`
- `access.permissions.role_only_management_access.allowed_roles = ['membership_owner', 'membership_manager']`
- `access.permissions.prevent_owner_assignment = true`

### `membership`

- `membership.strategy = cascade`
- `membership.resolution.prefer_current_cycle = true`
- `membership.seat_limits.tier_max_assignments.MAS Sustaining = 3`
- `membership.seat_limits.tier_max_assignments.Sustaining = 3`
- `membership.seat_limits.tier_max_assignments.Joint Sustaining = 6`

### `member_management`

- `member_management.addition.auto_assign_roles = []`
- `member_management.addition.protected_relationship_types = ['company_admin']`
- `member_management.forms.add_member.fields.first_name.enabled = true`
- `member_management.forms.add_member.fields.first_name.required = true`
- `member_management.forms.add_member.fields.last_name.enabled = true`
- `member_management.forms.add_member.fields.last_name.required = true`
- `member_management.forms.add_member.fields.email.enabled = true`
- `member_management.forms.add_member.fields.email.required = true`
- `member_management.forms.add_member.fields.description.enabled = false`
- `member_management.forms.add_member.fields.permissions.enabled = true`
- `member_management.forms.add_member.fields.permissions.required = false`
- `member_management.forms.add_member.fields.permissions.allowlist = ['org_editor']`
- `member_management.forms.add_member.fields.permissions.denylist = ['membership_manager', 'membership_owner']`
- `member_management.forms.add_member.fields.relationship_type.enabled = false`
- `member_management.permissions_modal.allowlist = ['org_editor']`
- `member_management.permissions_modal.denylist = ['membership_manager', 'membership_owner']`
- `member_management.edit.require_active_membership_for_role_updates = true`

### `relationships`

- `relationships.defaults.type = regular_member`
- `relationships.filters.allowlist = ['company_admin', 'regular_member']`
- `relationships.filters.denylist = ['affiliate']`
- `relationships.display.member_card_active_only = true`
- `relationships.labels.custom.company_admin = Company Admin`
- `relationships.labels.custom.regular_member = Regular Member`
- `relationships.labels.custom.affiliate = Affiliate`

### `presentation`

- `presentation.relationships.show_type = true`
- `presentation.member_list.use_unified = true`
- `presentation.member_view.use_unified = true`
- `presentation.member_list.show_remove_button = false`
- `presentation.member_list.show_bulk_upload = false`
- `presentation.member_list.display_roles.allowlist = ['membership_owner', 'membership_manager', 'org_editor', 'member']`
- `presentation.member_list.display_roles.denylist = ['supplemental_member', 'cchlmembercommunity', 'cchl_member_community']`
- `presentation.member_list.account_status.enabled = true`
- `presentation.member_list.account_status.show_unconfirmed_label = true`
- `presentation.member_list.account_status.confirmed_tooltip = Account confirmed`
- `presentation.member_list.account_status.unconfirmed_tooltip = Has not confirmed their account`
- `presentation.member_list.account_status.unconfirmed_label = Has not confirmed their account`
- `presentation.member_list.seat_limit_message = You have reached the maximum number of assignable people under this membership.`
- `presentation.member_list.remove_policy_callout.enabled = true`
- `presentation.member_list.remove_policy_callout.placement = above_members`
- `presentation.member_list.remove_policy_callout.title = Remove Members`
- `presentation.member_list.remove_policy_callout.message = To remove a member from your organization, please contact MSA directly.`
- `presentation.member_list.remove_policy_callout.email = associationmanagement@microscopy.org`

### `integrations`

- `integrations.additional_seats.enabled = false`
- `integrations.additional_seats.sku = additional-seats`
- `integrations.additional_seats.discount_sku = corporate-seat-discount`
- `integrations.additional_seats.form_id = 0`
- `integrations.additional_seats.form_slug = additional-seats`
- `integrations.additional_seats.min_quantity = 1`
- `integrations.additional_seats.max_quantity = 900`
- `integrations.notifications.confirmation_email_from = associationmanagement@microscopy.org`

## Current Config Function

```php
function wicket_child_orgman_config(array $config): array
{
    $config['membership']['strategy'] = 'cascade';
    $config['membership']['resolution']['prefer_current_cycle'] = true;
    $config['member_management']['addition']['auto_assign_roles'] = [];

    // Protect company_admin connections during stale-relationship repair so onboarding relationships are preserved.
    $config['member_management']['addition']['protected_relationship_types'] = ['company_admin'];

    // Management permissions
    $config['access']['permissions']['manage_member_roles'] = ['membership_manager', 'membership_owner'];
    $config['access']['permissions']['add_member_roles'] = ['membership_manager', 'membership_owner'];
    $config['access']['permissions']['remove_member_roles'] = []; // disables remove endpoint authorization
    $config['access']['permissions']['role_only_management_access']['enabled'] = true;
    $config['access']['permissions']['role_only_management_access']['allowed_roles'] = ['membership_owner', 'membership_manager'];

    // Prevent owner role assignment from roster UI
    $config['access']['permissions']['prevent_owner_assignment'] = true;

    // Only Org Editor can be assigned from Add/Edit permissions UIs
    $config['member_management']['forms']['add_member']['fields']['permissions']['allowlist'] = ['org_editor'];
    $config['member_management']['forms']['add_member']['fields']['permissions']['denylist'] = ['membership_manager', 'membership_owner'];
    $config['member_management']['permissions_modal']['allowlist'] = ['org_editor'];
    $config['member_management']['permissions_modal']['denylist'] = ['membership_manager', 'membership_owner'];

    // Add form fields for required input + optional Org Editor only
    $config['member_management']['forms']['add_member']['fields']['first_name']['enabled'] = true;
    $config['member_management']['forms']['add_member']['fields']['first_name']['required'] = true;
    $config['member_management']['forms']['add_member']['fields']['last_name']['enabled'] = true;
    $config['member_management']['forms']['add_member']['fields']['last_name']['required'] = true;
    $config['member_management']['forms']['add_member']['fields']['email']['enabled'] = true;
    $config['member_management']['forms']['add_member']['fields']['email']['required'] = true;
    $config['member_management']['forms']['add_member']['fields']['description']['enabled'] = false;
    $config['member_management']['forms']['add_member']['fields']['permissions']['enabled'] = true;
    $config['member_management']['forms']['add_member']['fields']['permissions']['required'] = false;
    $config['member_management']['forms']['add_member']['fields']['relationship_type']['enabled'] = false;

    // Restrict edit-permissions updates to active members only
    $config['member_management']['edit']['require_active_membership_for_role_updates'] = true;

    // Relationship policy: include only Company Admin + Regular Member, exclude Affiliate.
    // Only active relationships are shown on member cards.
    $config['relationships']['defaults']['type'] = 'regular_member';
    $config['relationships']['filters']['allowlist'] = ['company_admin', 'regular_member'];
    $config['relationships']['filters']['denylist'] = ['affiliate'];
    $config['relationships']['display']['member_card_active_only'] = true;
    $config['presentation']['relationships']['show_type'] = true;

    // Unified member views + remove policy + seat limit behavior
    $config['presentation']['member_list']['use_unified'] = true;
    $config['presentation']['member_view']['use_unified'] = true;
    $config['presentation']['member_list']['show_remove_button'] = false;
    $config['presentation']['member_list']['show_bulk_upload'] = false;
    $config['presentation']['member_list']['display_roles']['allowlist'] = ['membership_owner', 'membership_manager', 'org_editor', 'member'];
    $config['presentation']['member_list']['display_roles']['denylist'] = ['supplemental_member', 'cchlmembercommunity', 'cchl_member_community'];
    $config['presentation']['member_list']['account_status']['enabled'] = true;
    $config['presentation']['member_list']['account_status']['show_unconfirmed_label'] = true;
    $config['presentation']['member_list']['account_status']['confirmed_tooltip'] = __('Account confirmed', 'wicket-acc');
    $config['presentation']['member_list']['account_status']['unconfirmed_tooltip'] = __('Has not confirmed their account', 'wicket-acc');
    $config['presentation']['member_list']['account_status']['unconfirmed_label'] = __('Has not confirmed their account', 'wicket-acc');
    $config['presentation']['member_list']['seat_limit_message'] = __('You have reached the maximum number of assignable people under this membership.', 'wicket-acc');
    $config['membership']['seat_limits']['tier_max_assignments'] = [
        'MAS Sustaining' => 3,
        'Sustaining' => 3,
        'Joint Sustaining' => 6,
    ];
    $config['presentation']['member_list']['remove_policy_callout'] = [
        'enabled' => true,
        'placement' => 'above_members', // or 'below_members'
        'title' => __('Remove Members', 'wicket-acc'),
        'message' => __('To remove a member from your organization, please contact MSA directly.', 'wicket-acc'),
        'email' => 'associationmanagement@microscopy.org',
    ];

    // Friendly labels for relationship display
    $config['relationships']['labels']['custom']['company_admin'] = __('Company Admin', 'wicket-acc');
    $config['relationships']['labels']['custom']['regular_member'] = __('Regular Member', 'wicket-acc');
    $config['relationships']['labels']['custom']['affiliate'] = __('Affiliate', 'wicket-acc');

    // Notification email content overrides (site-specific).
    $config['integrations']['notifications']['confirmation_email_from'] = 'associationmanagement@microscopy.org';
    $config['integrations']['additional_seats']['enabled'] = false;
    $config['integrations']['additional_seats']['sku'] = 'additional-seats';
    $config['integrations']['additional_seats']['discount_sku'] = 'corporate-seat-discount';
    $config['integrations']['additional_seats']['form_id'] = 0;
    $config['integrations']['additional_seats']['form_slug'] = 'additional-seats';
    $config['integrations']['additional_seats']['min_quantity'] = 1;
    $config['integrations']['additional_seats']['max_quantity'] = 900;

    return $config;
}
```
