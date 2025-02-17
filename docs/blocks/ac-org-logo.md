# AC Organization Logo Block Documentation

## Overview
The Organization Logo block manages the display and upload of organization logos. It handles image uploads, storage, and role-based access control for organization editors.

## Block Architecture

### Directory Structure
```
ac-org-logo/
├── block.json       # Block registration and settings
├── init.php        # Block initialization and logic
├── render.php      # Template renderer
└── block-styles.css # Logo styles
```

## Core Functionality

### Implementation Details

1. **File Management**
   ```php
   protected string $uploads_path = WICKET_ACC_UPLOADS_PATH . 'organization-logos/';
   protected string $uploads_url = WICKET_ACC_UPLOADS_URL . 'organization-logos/';
   protected array $pp_extensions = ['jpg', 'jpeg', 'png', 'gif'];
   ```
   - Dedicated upload directory for organization logos
   - Supported image formats: JPG, JPEG, PNG, GIF
   - Maximum file size control

2. **Access Control**
   - Role-based access (`org_editor` role)
   - Organization-specific permissions
   - Child organization support

### Features

1. **Organization Context**
   - Handles parent/child organization relationships
   - Organization ID detection from URL parameters
   - Multiple organization support

2. **Role Management**
   - Editor role validation
   - Organization-specific role checking
   - Permission inheritance for child organizations

3. **Upload Handling**
   - File type validation
   - Size restrictions
   - Secure file storage


- Upload failures
- Processing errors
- Permission denied

## Integration Points
- WordPress Media Library
- MDP Organization API
- Image processing library
- CDN integration (if configured)

## Related Documentation
- [Base Block](/blocks/base-block.md)
