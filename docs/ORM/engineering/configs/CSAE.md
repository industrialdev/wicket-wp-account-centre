---
title: "CSAE Configuration"
audience: [implementer, support, developer]
source_files: ["../csae-portal-wordpress/src/web/app/themes/wicket-child/custom/org-roster.php"]
---

# CSAE Configuration

Source of truth: `../csae-portal-wordpress/src/web/app/themes/wicket-child/custom/org-roster.php`

This document mirrors the current site override. If it drifts, update the site config first, then update this file.

## Active Strategy

- `membership.strategy = direct`

## Current Override Paths

### `access.roles`

- `access.roles.labels.membership_manager = Membership Manager`
- `access.roles.labels.org_editor = Organization Editor`
- `access.roles.labels.membership_owner = Membership Owner`

### `access.permissions`

- `access.permissions.manage_member_roles = ['membership_manager', 'membership_owner']`
- `access.permissions.add_member_roles = ['membership_manager', 'membership_owner']`
- `access.permissions.remove_member_roles = ['membership_manager', 'membership_owner']`
- `access.permissions.prevent_owner_removal = true`
- `access.permissions.owner_removal_requires_membership_owner_role = true`

### `membership`

- `membership.strategy = direct`

### `member_management`

- `member_management.edit.require_active_membership_for_role_updates = true`
- `member_management.removal.direct.preserve_relationship = true`
- `member_management.forms.add_member.fields.permissions.allowlist = ['org_editor', 'membership_manager']`
- `member_management.forms.add_member.fields.permissions.denylist = ['membership_owner']`
- `member_management.forms.add_member.fields.permissions.label = Additional Roles`
- `member_management.forms.add_member.fields.permissions.required = false`
- `member_management.forms.add_member.fields.description.enabled = false`
- `member_management.forms.add_member.fields.relationship_type.enabled = false`
- `member_management.permissions_modal.allowlist = ['org_editor', 'membership_manager']`
- `member_management.permissions_modal.denylist = ['membership_owner']`

### `presentation`

- `presentation.member_list.use_unified = true`
- `presentation.member_list.show_assignment_info = true`
- `presentation.member_list.display_roles.allowlist = ['membership_owner', 'membership_manager', 'org_editor', 'member']`
- `presentation.member_list.account_status.enabled = true`
- `presentation.member_list.account_status.show_unconfirmed_label = true`
- `presentation.member_list.account_status.unconfirmed_tooltip = Has not confirmed their account`
- `presentation.member_list.account_status.unconfirmed_label = Has not confirmed their account`
- `presentation.member_list.seat_limit_message = You have reached the maximum number of assignable people under this membership.`
- `presentation.member_card.fields.job_title.label = Title`
- `presentation.member_card.fields.description.enabled = false`

### `integrations.additional_seats`

- `integrations.additional_seats.enabled = false`
- `integrations.additional_seats.sku = additional-seats`
- `integrations.additional_seats.discount_sku = corporate-seat-discount`
- `integrations.additional_seats.form_id = 0`
- `integrations.additional_seats.form_slug = additional-seats`
- `integrations.additional_seats.min_quantity = 1`
- `integrations.additional_seats.max_quantity = 900`

### `removal`

- `removal.end_date_anchor = action_time`

## Current Config Function

```php
add_filter('wicket/org-roster/config', static function (array $config): array {
    // CSAE roster is direct-assignment based.
    $config['membership']['strategy'] = 'direct';

    // Keep role labels aligned with CASAE wording.
    $config['access']['roles']['labels']['membership_manager'] = __('Membership Manager', 'wicket-acc');
    $config['access']['roles']['labels']['org_editor'] = __('Organization Editor', 'wicket-acc');
    $config['access']['roles']['labels']['membership_owner'] = __('Membership Owner', 'wicket-acc');

    // Membership Managers can manage member roles; role updates only apply to active members.
    $config['access']['permissions']['manage_member_roles'] = ['membership_manager', 'membership_owner'];
    $config['access']['permissions']['add_member_roles'] = ['membership_manager', 'membership_owner'];
    $config['access']['permissions']['remove_member_roles'] = ['membership_manager', 'membership_owner'];
    $config['access']['permissions']['prevent_owner_removal'] = true;
    $config['access']['permissions']['owner_removal_requires_membership_owner_role'] = true;
    $config['member_management']['edit']['require_active_membership_for_role_updates'] = true;
    $config['member_management']['removal']['direct']['preserve_relationship'] = true;
    $config['removal']['end_date_anchor'] = 'action_time';

    // Restrict role assignment UI to the requested team-roster roles.
    $config['member_management']['forms']['add_member']['fields']['permissions']['allowlist'] = ['org_editor', 'membership_manager'];
    $config['member_management']['forms']['add_member']['fields']['permissions']['denylist'] = ['membership_owner'];
    $config['member_management']['forms']['add_member']['fields']['permissions']['label'] = __('Additional Roles', 'wicket-acc');
    $config['member_management']['forms']['add_member']['fields']['permissions']['required'] = false;
    $config['member_management']['forms']['add_member']['fields']['description']['enabled'] = false;
    $config['member_management']['forms']['add_member']['fields']['relationship_type']['enabled'] = false;
    $config['member_management']['permissions_modal']['allowlist'] = ['org_editor', 'membership_manager'];
    $config['member_management']['permissions_modal']['denylist'] = ['membership_owner'];

    // Team member list requirements: active-seat roster with status and security role visibility.
    $config['presentation']['member_list']['use_unified'] = true;
    $config['presentation']['member_list']['show_assignment_info'] = true;
    $config['presentation']['member_list']['display_roles']['allowlist'] = ['membership_owner', 'membership_manager', 'org_editor', 'member'];
    $config['presentation']['member_list']['account_status']['enabled'] = true;
    $config['presentation']['member_list']['account_status']['show_unconfirmed_label'] = true;
    $config['presentation']['member_list']['account_status']['unconfirmed_tooltip'] = __('Has not confirmed their account', 'wicket-acc');
    $config['presentation']['member_list']['account_status']['unconfirmed_label'] = __('Has not confirmed their account', 'wicket-acc');
    $config['presentation']['member_list']['seat_limit_message'] = __('You have reached the maximum number of assignable people under this membership.', 'wicket-acc');

    // Match "Title" wording in roster cards; hide description field.
    $config['presentation']['member_card']['fields']['job_title']['label'] = __('Title', 'wicket-acc');
    $config['presentation']['member_card']['fields']['description']['enabled'] = false;

    // CSAE does not use additional seat purchasing.
    $config['integrations']['additional_seats']['enabled'] = false;
    $config['integrations']['additional_seats']['sku'] = 'additional-seats';
    $config['integrations']['additional_seats']['discount_sku'] = 'corporate-seat-discount';
    $config['integrations']['additional_seats']['form_id'] = 0;
    $config['integrations']['additional_seats']['form_slug'] = 'additional-seats';
    $config['integrations']['additional_seats']['min_quantity'] = 1;
    $config['integrations']['additional_seats']['max_quantity'] = 900;

    return $config;
});
```
