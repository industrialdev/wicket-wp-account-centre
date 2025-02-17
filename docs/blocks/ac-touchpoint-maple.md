# AC Touchpoint Maple Block Documentation

## Overview
The Touchpoint Maple block displays course enrollment and completion data from Aptify Conversion (historical data) and Maple LMS. It provides a configurable interface for viewing course status with pagination support.

## Block Architecture

### Directory Structure
```
ac-touchpoint-maple/
├── block.json       # Block registration and settings
├── init.php        # Block initialization and logic
├── render.php      # Template renderer
└── block-styles.css # Maple styles
```

## Core Functionality

### Implementation Details

1. **Block Configuration**
   - Title customization
   - Touchpoint action selection (enrolled/completed)
   - Results per page setting
   - View more toggle
   - Block ID generation

2. **Course Display**
   - Filter by enrollment status
   - Pagination support
   - Dynamic loading of results
   - Card-based layout

3. **Data Integration**
   - Service ID handling (Aptify Conversion/Maple LMS)
   - Touchpoint filtering by action
   - User-specific data retrieval
   - MDP API integration

### Features

1. **Display Options**
   - Enrolled courses view
   - Completed courses view
   - Configurable results per page
   - Card-based layout support

2. **Navigation**
   - Load more functionality
   - Dynamic offset handling
   - Clean URL parameters
   - Block-specific IDs

3. **Course Cards**
   - Course details display
   - Status indication
   - Responsive layout
   - Consistent styling

4. **Preview Mode**
   - Block information display
   - Description of functionality
   - Clear preview formatting

5. **Integration**
   - Historical data support (Aptify)
   - Maple LMS integration
   - MDP API touchpoint service
   - User-specific data retrieval
