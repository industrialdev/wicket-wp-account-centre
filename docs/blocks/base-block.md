# AC Base Block Documentation

## Overview
The AC Base Block serves as a template for creating new Account Centre blocks. It demonstrates the basic structure and common functionality that blocks should implement.

## Block Architecture

### Directory Structure
```
_ac-base-block/
├── block.json       # Block registration and settings
├── init.php        # Block initialization and logic
├── render.php      # Template renderer
└── templates/     # Block templates
    ├── base-block.php  # Main template
    ├── error.php      # Error template
    └── success.php    # Success template
```

## Core Functionality

### Block Class Structure
```php
namespace WicketAcc\Blocks\BaseBlock;

use WicketAcc\WicketAcc;

class init extends WicketAcc {
    protected array $block = [];
    protected bool $is_preview = false;
    protected ?Blocks $blocks = null;

    public function __construct(
        array $block = [],
        bool $is_preview = false,
        ?Blocks $blocks = null
    );

    protected function display_block(): void;
    protected function process_form(): bool|void;
}
```

### Implementation Details

1. **Block Initialization**
   - Constructor parameter handling
   - Preview mode support
   - Block data management
   - Template rendering setup

2. **Form Processing**
   - Nonce verification
   - Admin check prevention
   - Form data sanitization
   - Action validation

3. **Template Management**
   - Success template rendering
   - Error template rendering
   - Base template rendering
   - Dynamic argument passing

### Features to Implement

1. **Required Methods**
   - `__construct()`: Block initialization
   - `display_block()`: Content rendering
   - `process_form()`: Form handling

2. **Security Measures**
   - WordPress nonce verification
   - Admin area protection
   - Input sanitization
   - Form action validation

3. **Template Structure**
   - Base template for content
   - Error template for failures
   - Success template for confirmations

### Integration Points

1. **WordPress Core**
   - Nonce system
   - Admin detection
   - Form processing
   - Template rendering

2. **WicketAcc Framework**
   - Block registration
   - Template management
   - Error handling
   - Form processing

### Error Handling

1. **Form Processing**
   - Invalid nonce handling
   - Missing action handling
   - Admin area protection
   - Process result handling

2. **Template Fallbacks**
   - Error template display
   - Success confirmation
   - Base content fallback

## Creating a New Block

1. **Directory Setup**
   ```bash
   # Create block directory
   mkdir -p ac-new-block/templates
   
   # Copy base files
   cp _ac-base-block/block.json ac-new-block/
   cp _ac-base-block/init.php ac-new-block/
   cp _ac-base-block/render.php ac-new-block/
   cp -r _ac-base-block/templates/* ac-new-block/templates/
   ```

2. **Update Namespace**
   ```php
   namespace WicketAcc\Blocks\NewBlock;
   ```

3. **Modify block.json**
   ```json
   {
       "name": "wicket-ac/new-block",
       "title": "New Block",
       "category": "wicket-blocks"
   }
   ```

4. **Implement Required Methods**
   - Extend constructor if needed
   - Override display_block()
   - Add form processing if required

5. **Create Templates**
   - Modify base template
   - Update error handling
   - Customize success messages
