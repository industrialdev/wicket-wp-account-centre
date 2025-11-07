# AC Welcome Block Documentation

## Overview
The Welcome block provides a personalized greeting for Account Centre users. It displays user information including their profile picture, name, membership details, and optional profile editing functionality.

## Block Architecture

### Directory Structure
```
ac-welcome/
├── block.json       # Block registration and settings
├── init.php        # Block initialization and logic
├── render.php      # Template renderer
└── block-styles.css # Welcome styles
```

## Core Functionality

### Implementation Details

1. **User Information Display**
   - Profile picture integration with WordPress avatar system
   - Multilingual support through WPML
   - Current user and Wicket person data retrieval
   - Customizable welcome message
   - Name display with filter support

2. **Membership Information**
   - Active memberships retrieval and display
   - Membership type categorization
   - Organization membership details
   - Membership date handling (start/end)
   - Optional member ID display

3. **Profile Management**
   - Configurable edit profile button
   - Flexible link handling (ACF or default)
   - Page mapping through ACF options
   - Custom URL support

### Features

1. **User Profile Display**
   - Avatar display (300px size)
   - Localized welcome message
   - Full name with filter support
   - Custom action hooks for name extensions
   - Responsive layout classes

2. **Membership Management**
   - Multiple membership support
   - Duplicate membership filtering
   - Organization relationship display
   - Active member status indication
   - Date formatting for membership periods

3. **ACF Configuration Options**
   - Edit profile button toggle
   - Member ID display toggle
   - Member since date toggle
   - Renewal date toggle
   - Profile link customization

4. **Theme Integration**
   - Version 2 theme support
   - Responsive grid layout
   - Consistent spacing classes
   - Conditional styling
   - Accessibility features

### Integration Points

1. **WordPress Integration**
   - User system integration
   - Avatar system
   - Page linking
   - Translation support

2. **Wicket Integration**
   - Person API
   - MDP API for organization data
   - Membership data retrieval
   - Custom filters for membership display

3. **WPML Integration**
   - Language code handling
   - Locale fallback support
   - Translation filters

### Error Handling
- Language code fallback
- Empty membership handling
- Profile link validation
- Date validation
- Image URL validation

## Events & Hooks


### Filters

#### <a id="wicket-acc-block-welcome-non-member-text"></a>`wicket/acc/block/welcome_non_member_text`

This filter allows you to customize the text displayed for users who are not members ("Non-Member") in the AC Welcome block.

**Usage:**
```php
add_filter('wicket/acc/block/welcome_non_member_text', function($default_text) {
      return __('Guest User', 'wicket-acc'); // Replace with your custom text
});
```

- **Parameters:**
   - `$default_text` (`string`): The default text, usually `__('Non-Member', 'wicket-acc')`.
- **Return:**
   - `string` The text to display for non-members.

**Example:**
```php
add_filter('wicket/acc/block/welcome_non_member_text', function($default_text) {
      return __('Visitor', 'wicket-acc');
});
```
