# IAA Configuration

Source of truth: `../iaa-website-wordpress/src/web/app/themes/wicket-child/custom/org-roster.php`

This document mirrors the current site override. If it drifts, update the site config first, then update this file.

## Active Strategy

- `membership.strategy = groups`

## Current Override Paths

### `access`

- `access.roles.manager = membership_manager`

### `membership`

- `membership.strategy = groups`

### `groups`

- `groups.matching.tag_name = Roster Management`
- `groups.matching.tag_case_sensitive = false`
- `groups.roles.management = ['president', 'delegate', 'alternate_delegate', 'correspondent']`
- `groups.roles.roster = ['member', 'observer']`
- `groups.roles.seat_limited = ['member']`
- `groups.additional_info.key = association`
- `groups.additional_info.value_field = name`
- `groups.additional_info.fallback_to_org_uuid = true`
- `groups.removal.mode = end_date`
- `groups.removal.end_date_anchor = day_start_utc`
- `groups.ui.add_member_auto_close_on_success = true`
- `groups.ui.add_member_auto_close_delay_seconds = 7`
- `groups.presentation.add_member_auto_close_on_success = true`
- `groups.presentation.add_member_auto_close_delay_seconds = 7`

### `presentation`

- `presentation.organization_details.show_actions = false` — hides the Org. Profile / Manage Members / Bulk Upload action buttons from the organization details summary card.
- `presentation.organization_list.show_my_role = false` — hides the "My Role" display from organization summary cards.
- `presentation.organization_list.page_size = 10`

### `presentation.member_list`

- `presentation.member_list.show_assignment_info = false` — hides the seats/assignment count summary above the member list.

### `presentation.member_view`

- `presentation.member_view.add_member_auto_close_on_success = true`
- `presentation.member_view.add_member_auto_close_delay_seconds = 7`
- `member_management.forms.add_member.clear_form_on_error = true`

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
function wicket_child_orgman_config(array $config): array
{
    $config['membership']['strategy'] = 'groups';

    // Access control
    $config['access']['roles']['manager'] = 'membership_manager';

    // Group matching
    $config['groups']['matching']['tag_name'] = 'Roster Management';
    $config['groups']['matching']['tag_case_sensitive'] = false;

    // Roles and Limits
    $config['groups']['roles']['management'] = [
        'president',
        'delegate',
        'alternate_delegate',
        'correspondent',
    ];
    $config['groups']['roles']['roster'] = [
        'member',
        'observer',
    ];
    $config['groups']['roles']['seat_limited'] = [
        'member',
    ];

    // Scoping
    $config['groups']['additional_info'] = [
        'key' => 'association',
        'value_field' => 'name',
        'fallback_to_org_uuid' => true,
    ];

    // Removal policy
    $config['groups']['removal']['mode'] = 'end_date';
    $config['groups']['removal']['end_date_anchor'] = 'day_start_utc';

    // UI and Presentation
    $config['presentation']['organization_details']['show_actions'] = false;
    $config['presentation']['organization_list']['show_my_role'] = false;
    $config['presentation']['organization_list']['page_size'] = 10;
    $config['presentation']['member_list']['show_assignment_info'] = false;
    $config['member_management']['forms']['add_member']['clear_form_on_error'] = true;

    $config['presentation']['member_view']['add_member_auto_close_on_success'] = true;
    $config['presentation']['member_view']['add_member_auto_close_delay_seconds'] = 7;
    $config['groups']['ui']['add_member_auto_close_on_success'] = true;
    $config['groups']['ui']['add_member_auto_close_delay_seconds'] = 7;
    $config['groups']['presentation']['add_member_auto_close_on_success'] = true;
    $config['groups']['presentation']['add_member_auto_close_delay_seconds'] = 7;

    // Integrations
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
