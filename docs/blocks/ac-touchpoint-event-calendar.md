# AC Touchpoint Event Calendar Block Documentation

## Overview
The Touchpoint Event Calendar block displays event data from The Events Calendar (TEC) integration. It provides a configurable interface for viewing upcoming and past events, with support for filtering and pagination.

## Block Architecture

### Directory Structure
```
ac-touchpoint-event-calendar/
├── block.json       # Block registration and settings
├── init.php        # Block initialization and logic
├── render.php      # Template renderer
└── block-styles.css # Calendar styles
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

3. **Action Filtering**
   - RSVP to event
   - Event registration
   - Event attendance
   - Custom action filtering

### Features

1. **Display Options**
   - Upcoming events view
   - Past events view
   - All events view
   - Configurable results per page
   - Multi-column layout support

2. **Navigation**
   - Block-specific URL parameters
   - Toggle between event views
   - Load more functionality
   - Clean URL handling

3. **Event Cards**
   - Event details display
   - Action-based filtering
   - Responsive layout
   - Consistent styling

4. **Preview Mode**
   - Block information display
   - Description of functionality
   - Clear preview formatting

5. **Integration**
   - The Events Calendar integration
   - MDP API touchpoint service
   - User-specific data retrieval
