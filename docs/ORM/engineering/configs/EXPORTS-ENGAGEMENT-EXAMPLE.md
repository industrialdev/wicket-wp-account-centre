---
title: "Exports & Engagement Configuration Example"
audience: [implementer, support, developer]
config_path: exports, engagement
php_class: WicketORM\Services\MemberExportService
source_files: ["src/Services/MemberExportService.php", "src/Services/EngagementService.php", "src/Controllers/MemberExportController.php", "src/Controllers/EngagementController.php"]
---

# Exports & Engagement Configuration Example

This document shows how to enable and configure the member export and engagement features. These features are **disabled by default** and must be explicitly enabled via the `wicket/org-roster/config` filter.

## Feature Overview

### Member Export
- Async, batch-processed CSV export of all org members
- Secure download tokens with expiration and max-download limits
- WP-Cron based processing for large datasets
- Configurable CSV columns and batch size

### Engagement Display
- MDP engagement/donation data display on member cards
- Configurable sections (Foundation, PAC, custom)
- Badge parsing from person tags
- Per-field formatting (currency, date, yesno, string)
- Active membership conditional rendering

## Enabling Both Features

```php
add_filter('wicket/org-roster/config', function ($config) {
    // Enable async CSV member export
    $config['exports']['enabled'] = true;
    
    // Enable engagement data display
    $config['engagement']['enabled'] = true;
    
    // Specify which orgs to check for active membership
    // (used by PAC-style conditional sections)
    $config['engagement']['member_org_uuids'] = [
        '3268cc80-cad8-4c44-af3d-b12abf637511',
        'a7288121-e793-4e14-bbaa-0f775dde39b8',
    ];
    
    return $config;
});
```

## Export Configuration

### Basic Export Setup

```php
$config['exports'] = [
    'enabled' => true,
    'batch_size' => 50,              // Members processed per cron batch
    'token_expiration_days' => 30,   // Download link expiration
    'max_downloads' => 10,           // Max uses per download link
    'upload_dir_slug' => 'wicket-exports',
];
```

### Custom CSV Columns

```php
$config['exports']['columns'] = [
    'first_name' => true,
    'last_name' => true,
    'email' => true,
    'job_title' => true,
    'permission_role' => true,
    'primary_role' => true,
    // Add custom columns as needed
];
```

### Export REST Endpoints

- `POST /org-management/v1/exports/initiate` — Start export job
- `GET /org-management/v1/exports/status` — Poll job status

### Export Flow

1. User clicks "Export Members" in member list toolbar
2. Modal appears; user confirms export
3. Job is queued via WP-Cron; CSV generated in batches
4. User receives email with secure download link
5. Link expires after configured days or max downloads

## Engagement Configuration

### Basic Engagement Setup

```php
$config['engagement'] = [
    'enabled' => true,
    'member_org_uuids' => [
        '3268cc80-cad8-4c44-af3d-b12abf637511',
    ],
];
```

### Custom Engagement Sections

```php
$config['engagement']['sections'] = [
    'foundation' => [
        'enabled' => true,
        'label' => __('Foundation', 'your-text-domain'),
        'requires_active_membership' => false,
        'fields' => [
            'current_fy' => [
                'mdp_key' => 'fdn_current_fy',
                'label' => __('Current FY', 'your-text-domain'),
                'format' => 'currency',
            ],
            'last_giving_dt' => [
                'mdp_key' => 'fdn_last_giving_dt',
                'label' => __('Last Gift', 'your-text-domain'),
                'format' => 'date',
            ],
            'lifetime_level' => [
                'mdp_key' => 'fdn_lifetime_level',
                'label' => __('Lifetime Level', 'your-text-domain'),
                'format' => 'string',
            ],
        ],
        'badge_pattern' => '/^fdn_Donor_FY(\d{2})$/',
        'badge_label_template' => 'Foundation Donor FY{year}',
    ],
    'pac' => [
        'enabled' => true,
        'label' => __('PAC', 'your-text-domain'),
        'requires_active_membership' => true,  // Only for active members
        'fields' => [
            'current_fy' => [
                'mdp_key' => 'pac_current_fy',
                'label' => __('Current FY', 'your-text-domain'),
                'format' => 'currency',
            ],
        ],
        'badge_pattern' => '/^DonorPAC_FY(\d{2})$/',
        'badge_label_template' => 'PAC Donor FY{year}',
    ],
];
```

### Field Format Options

- `currency`: Formats as `$1,234.56`
- `date`: Formats using WordPress date format
- `yesno`: Converts `yes`/`1`/`true` to "Yes", everything else to "No"
- `string`: Passes through `sanitize_text_field()`

### Engagement REST Endpoint

- `GET /org-management/v1/engagement/person` — Fetch engagement data for person

### Display Behavior

- Foundation section: Always shown (if enabled)
- PAC section: Only shown when person is an active member of configured orgs
- Badges: Parsed from person tags matching each section's `badge_pattern`
- Data: Fetched from person/organization `data_fields` attribute in MDP response

## Active Membership Check

The `requires_active_membership` flag controls whether a section is rendered based on the person's membership status:

```php
$config['engagement']['member_org_uuids'] = [
    'org-uuid-1',
    'org-uuid-2',
];
```

When a person has an active membership in any of the listed orgs, sections with `requires_active_membership => true` will be displayed.

## Security Considerations

### Export Security
- Download tokens are one-time-use with expiration
- Max download limit prevents link sharing abuse
- Files stored in WordPress uploads directory (not webroot)
- Nonce verification on export initiation

### Engagement Security
- Only shows data for persons user can view (permission checks)
- Active membership check prevents data leakage
- All field values sanitized via `sanitize_text_field()`

## Performance Notes

### Export Performance
- WP-Cron batching prevents timeouts on large exports
- Default batch size of 50 balances memory and speed
- Increase batch size for faster processing (more memory)
- Decrease batch size for slower processing (less memory)

### Engagement Performance
- API responses cached via WordPress transients
- Badge parsing is regex-based (relatively fast)
- Consider caching for high-traffic member views

## Troubleshooting

### Export Not Appearing
- Verify `exports.enabled` is `true`
- Check user has `can_manage_org_members` capability
- Ensure member list toolbar is rendered (not custom template)

### Export Jobs Stuck
- Check WP-Cron is running: `wp cron event list`
- Manually trigger cron: `wp cron event run --due-now`
- Review error logs: `wc_get_logger()->get('wicket-orgman')`

### Engagement Data Missing
- Verify `engagement.enabled` is `true`
- Check MDP `data_fields` attribute contains expected keys
- Ensure `member_org_uuids` includes the person's org UUID
- Test API response: `wicket_api_client()->get("/people/{uuid}")`

### Active Membership Check Failing
- Verify person has active `organization_memberships` record
- Check `member_org_uuids` includes correct org UUIDs
- Test membership lookup: `wicket_get_person_active_memberships($person_uuid)`

## Complete Example Function

```php
add_filter('wicket/org-roster/config', function ($config) {
    // Enable exports
    $config['exports']['enabled'] = true;
    $config['exports']['batch_size'] = 100;
    $config['exports']['token_expiration_days'] = 14;
    $config['exports']['max_downloads'] = 5;
    
    // Enable engagement
    $config['engagement']['enabled'] = true;
    $config['engagement']['member_org_uuids'] = [
        '3268cc80-cad8-4c44-af3d-b12abf637511',
    ];
    
    // Customize Foundation section
    $config['engagement']['sections']['foundation']['fields']['lifetime_level']['label'] = 
        __('Donor Level', 'your-text-domain');
    
    // Add custom section
    $config['engagement']['sections']['special_events'] = [
        'enabled' => true,
        'label' => __('Special Events', 'your-text-domain'),
        'requires_active_membership' => false,
        'fields' => [
            'gala_attendance' => [
                'mdp_key' => 'gala_2024_attended',
                'label' => __('Gala 2024', 'your-text-domain'),
                'format' => 'yesno',
            ],
        ],
        'badge_pattern' => '/^event_(\w+)_attended$/',
        'badge_label_template' => 'Event: {event}',
    ];
    
    return $config;
});
```
