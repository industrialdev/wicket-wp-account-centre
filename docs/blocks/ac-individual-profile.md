# AC Individual Profile Block Documentation

## Overview
The Individual Profile block provides a wrapper for the Wicket Individual Profile widget component. This block is designed to display and manage individual user profile information through the Wicket platform.

## Block Architecture

### Directory Structure
```
ac-individual-profile/
├── block.json       # Block registration and settings
├── init.php        # Block initialization
├── render.php      # Template renderer
└── block-styles.css # Profile styles
```

## Core Functionality

### Implementation Details

1. **Widget Integration**
   ```php
   get_component('widget-profile-individual', []);
   ```
   The block serves as a wrapper for the `widget-profile-individual` component, which handles:
   - Profile data display
   - Edit functionality
   - Data synchronization with MDP

2. **Dependencies**
   - Requires the Wicket platform integration
   - Uses the global widget component system
   - Relies on MDP for data management

### Features

1. **Profile Management**
   - View personal information
   - Edit profile details
   - Data synchronization with MDP

2. **Integration**
   - Seamless integration with Wicket platform
   - Consistent user interface
   - Standardized data handling

### Usage Notes

1. **Implementation**
   - Block requires no additional configuration
   - Automatically uses current user context
   - Handles all profile management through the widget

2. **Customization**
   - Visual styling through block-styles.css
   - Functionality controlled by widget component
   - No additional block-level settings required


- File upload handling
- Cache management

## Related Documentation
- [Base Block](/blocks/base-block.md)
