# AC Profile Picture Block Documentation

## Overview
The Profile Picture block manages user profile image uploads and display. It handles image validation, cropping, storage, and provides a simple interface for users to manage their profile pictures.

## Block Architecture

### Directory Structure
```
ac-profile-picture/
├── block.json       # Block registration and settings
├── init.php        # Block initialization and logic
├── render.php      # Template renderer
└── block-styles.css # Profile picture styles
```

## Core Functionality

### Implementation Details

1. **File Management**
   - Configurable upload path: `WICKET_ACC_UPLOADS_PATH . 'profile-pictures/'`
   - Supported extensions: jpg, jpeg, png, gif
   - Maximum file size setting via ACF
   - Automatic file cleanup on updates

2. **Image Processing**
   - Image validation
   - Square cropping
   - File type verification
   - Size limit enforcement
   - Existing file management

3. **Form Handling**
   - Secure nonce verification
   - File upload processing
   - Remove picture functionality
   - Error handling and feedback

### Features

1. **Upload Management**
   - File size validation
   - Image type verification
   - Automatic file renaming
   - Directory creation if needed

2. **Image Processing**
   - Square cropping functionality
   - Center-based cropping
   - Temporary file handling
   - Clean file replacement

3. **Security**
   - Nonce verification
   - File type validation
   - Size restrictions
   - Admin access checks

4. **User Interface**
   - Current picture display
   - Upload form
   - Remove picture option
   - Success/error messaging
