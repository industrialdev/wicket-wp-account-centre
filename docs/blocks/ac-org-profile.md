# AC Organization Profile Block Documentation

## Overview
The Organization Profile block manages organization profile information, supporting both parent and child organizations. It integrates with WPML for multilingual support and provides configurable display options.

## Block Architecture

### Directory Structure
```
ac-org-profile/
├── block.json       # Block registration and settings
├── init.php        # Block initialization and logic
├── render.php      # Template renderer
└── block-styles.css # Profile styles
```

## Core Functionality

### Implementation Details

1. **Block Configuration**
   ```php
   protected array $block = [];
   protected bool $is_preview = false;
   protected int|string|null|bool $hide_additional_info = 0;
   ```
   - Configurable additional info display
   - Preview mode support
   - Block-specific settings

2. **Organization Context**
   - URL-based organization detection
   - Parent/child organization handling
   - Multiple organization support

3. **Language Support**
   ```php
   $lang = WACC()->Language()->getCurrentLanguage();
   ```
   - Centralized language detection (WPML, Polylang, WP User/Site Locale)
   - Consistent language handling via `Language` class
   - Default fallback to 'en'

### Features

1. **Organization Management**
   - Parent organization handling
   - Child organization support
   - Organization ID detection from URL

2. **Display Options**
   - Configurable additional info visibility
   - Preview mode for editors
   - Responsive layout

3. **Integration**
   - WPML compatibility
   - URL parameter handling
   - Dynamic organization loading

### Widget Integration

1. **Organization Profile Widget**
   ```javascript
   Wicket.widgets.editOrganizationProfile({
       rootEl: widgetRoot,
       apiRoot: wicket_settings['api_endpoint'],
       accessToken: access_token,
       orgId: org_id,
       lang: lang
   })
   ```
   - Profile editing interface
   - Language-specific content
   - Event handling for save operations

2. **Additional Info Widget**
   ```javascript
   Wicket.widgets.editAdditionalInfo({
       loadIcons: true,
       rootEl: widgetRoot,
       apiRoot: wicket_settings['api_endpoint'],
       accessToken: access_token,
       resource: {
           type: "organizations",
           id: org_id
       },
       lang: lang
   })
   ```
   - Optional display based on settings
   - Icon support
   - Schema-based data management

### Access Control
- Role-based access via `org_editor` role
- Organization-specific permissions
- Automatic organization detection from roles

## Features

### Form Management
- Real-time validation
- Field dependencies
- Dynamic updates
- Progress tracking

### Data Integration
- MDP synchronization
- Cache management
- Batch operations
- Error recovery

### UI Components
- Form sections
- Validation messages
- Status indicators
- Loading states
- Action buttons

## Error Handling
- Field validation
- API failures
- Permission errors
- Network issues
- Data conflicts

## Related Documentation
- [Base Block](/blocks/base-block.md)
