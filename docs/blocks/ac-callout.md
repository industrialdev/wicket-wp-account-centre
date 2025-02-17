# AC Callout Block Documentation

## Overview
The Callout block provides dynamic notification displays within the Account Centre, particularly focused on membership-related messages. It supports multiple logic modes and integrates with WooCommerce Subscriptions and Wicket Memberships.

## Block Architecture

### Directory Structure
```
ac-callout/
├── block.json       # Block registration and settings
├── init.php        # Block initialization and logic
├── render.php      # Template renderer
└── block-styles.css # Callout styles
```

## Core Functionality

### Block Configuration Fields

#### Basic Settings
```php
[
    'block_logic' => string,           // Logic mode for callout display
    'renewal_period' => integer,        // Days before expiry for renewal notice
    'ac_callout_title' => string,      // Callout title
    'ac_callout_description' => string, // Callout description
    'ac_callout_links' => array        // Action links
]
```

#### Logic Modes
1. **Become Member (`become_member`)**
   - Handles pending membership applications
   - Integrates with WooCommerce orders and subscriptions
   - Supports multi-language with WPML

2. **Membership Renewal (`membership_renewal`)**
   - Displays renewal notices based on expiration dates
   - Configurable renewal period
   - Supports both Wicket and WooCommerce memberships

3. **Profile Completion (`profile_completion`)**
   - Tracks mandatory field completion
   - Customizable field requirements
   - Multi-step profile completion tracking

### Features

1. **WooCommerce Integration**
   - Order status tracking
   - Subscription product detection
   - Membership tier association

2. **Membership Management**
   - Active membership detection
   - Expiration tracking
   - Renewal workflow

3. **Profile Management**
   - Field completion tracking
   - Mandatory field validation
   - Progress indication

4. **Multilingual Support**
   - WPML integration
   - Language-specific content
   - ISO code handling

### Integration Points

1. **Filters**
   ```php
   // Filter product categories for membership detection
   apply_filters('wicket/acc/renewal-filter-product-data', $membership_cats)

   // Filter current language
   apply_filters('wpml_current_language', null)
   ```

2. **External Dependencies**
   - WooCommerce Subscriptions
   - Wicket Memberships
   - WPML (optional)

### Conditional Display

- Role-based visibility
- Dismissible option
- Persistent state tracking
- Conditional rendering

### Content Support
- Rich text editor
- Dynamic content tags
- HTML support
- Icon integration

### User Interaction
- Dismissal handling
- State persistence
- Animation effects
- Accessibility support

### Supported Styles
- `wicket-callout--info`
- `wicket-callout--warning`
- `wicket-callout--error`
- `wicket-callout--success`
- `wicket-callout--custom`

## ACF Fields Configuration

### Message Settings
```php
[
    'key' => 'field_message',
    'label' => 'Message Content',
    'name' => 'message',
    'type' => 'wysiwyg',
    'required' => true
],
[
    'key' => 'field_style',
    'label' => 'Callout Style',
    'name' => 'style',
    'type' => 'select',
    'choices' => [
        'info' => 'Information',
        'warning' => 'Warning',
        'error' => 'Error',
        'success' => 'Success'
    ]
]
```

## Error Handling
- Invalid style fallbacks
- Missing content handling
- Role validation errors
- Dismissal state errors

## Related Documentation
- [Base Block](/blocks/base-block.md)
