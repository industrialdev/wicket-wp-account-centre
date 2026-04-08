# AC Touchpoint Pheedloop Block Documentation

## Overview
The Touchpoint Pheedloop block displays event data from the Aptify Conversion integration. It provides a configurable interface for viewing upcoming and past events, with support for filtering and pagination.

## Block Architecture

### Directory Structure
```
ac-touchpoint-pheedloop/
├── block.json       # Block registration and settings
├── init.php        # Block initialization and logic
├── render.php      # Template renderer
└── block-styles.css # Pheedloop styles
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
   - Aptify Conversion service integration
   - Touchpoint data filtering
   - User-specific data retrieval
   - MDP API integration

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
   - Event start date display
   - Event details
   - Responsive layout
   - Consistent styling

4. **Preview Mode**
   - Block information display
   - Description of functionality
   - Clear preview formatting

5. **Integration**
   - Aptify Conversion integration
   - MDP API touchpoint service
   - User-specific data retrieval
