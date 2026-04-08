# AC Touchpoint VitalSource Block Documentation

## Overview
The Touchpoint VitalSource block displays eBook fulfillment data from the Aptify Conversion integration. It provides a paginated interface for viewing user's eBook access and details.

## Block Architecture

### Directory Structure
```
ac-touchpoint-vitalsource/
├── block.json       # Block registration and settings
├── init.php        # Block initialization and logic
├── render.php      # Template renderer
└── block-styles.css # Block styles
```

## Core Functionality

### Implementation Details

1. **Block Configuration**
   - Title customization
   - Results per page setting
   - View more toggle option
   - Pagination support

2. **Data Integration**
   - Aptify Conversion service integration
   - Touchpoint data filtering by 'eBook Fulfillment' action
   - User-specific data retrieval
   - MDP API integration

3. **Display Features**
   - Product name display
   - Pagination with load more functionality
   - Dynamic URL parameters
   - Clean URL handling

### Features

1. **Display Options**
   - Configurable results per page
   - Load more functionality
   - Custom title setting

2. **eBook Card Display**
   - Product name
   - Fulfillment details
   - Responsive layout
   - Consistent styling

3. **Preview Mode**
   - Block information display
   - Description of functionality
   - Clear preview formatting

4. **Integration**
   - Aptify Conversion integration (used in place of VitalSource for historical data)
   - MDP API touchpoint service
   - User-specific data retrieval
   - Action-based filtering ('eBook Fulfillment')

### Error Handling
- Empty data state messaging
- Pagination boundary checks
- Data validation
- Safe URL parameter handling
