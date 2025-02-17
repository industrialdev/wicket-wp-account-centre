# AC Touchpoint Cvent Block Documentation

## Overview
The Touchpoint Cvent block displays Cvent event registrations for the current user. It provides a configurable interface for viewing upcoming and past events, with customizable display options and pagination support.

## Block Architecture

### Directory Structure
```
ac-touchpoint-cvent/
├── block.json       # Block registration and settings
├── init.php        # Block initialization and logic
├── render.php      # Template renderer
└── block-styles.css # Cvent styles
```

## Core Functionality

### Implementation Details

1. **Block Configuration**
   - Title customization
   - Default display mode (upcoming/past)
   - Results per page setting
   - Past events link customization
   - View more events toggle
   - Column layout options

2. **Event Display**
   - Filter by upcoming/past/all events
   - Pagination support
   - Dynamic loading of results
   - Column-based layout options

3. **Data Integration**
   - MDP API integration
   - Touchpoint service handling
   - User-specific data retrieval

### Features

1. **Display Options**
   - Upcoming events view
   - Past events view
   - All events view
   - Configurable results per page
   - Multi-column layout support

2. **Navigation**
   - Toggle between event views
   - Load more functionality
   - Dynamic URL parameters
   - Clean URL handling

3. **Event Cards**
   - Start time display
   - Event details
   - Responsive layout
   - Consistent styling

4. **Preview Mode**
   - Block information display
   - Description of functionality
   - Clear preview formatting
