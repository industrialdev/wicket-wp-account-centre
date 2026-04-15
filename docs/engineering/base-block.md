---
title: "Base Block"
audience: [developer, agent]
php_class: Wicket_ACC_Main
source_files: ["src/"]
---

# AC Base Block Documentation

## Overview
The `_ac-base-block` serves as the blueprint for all Account Centre blocks. It provides a standardized structure for initialization, template rendering, and form processing.

## Block Architecture

### Directory Structure
```
ac-new-block/
├── block.json       # Registration, metadata, and ACF configuration
├── init.php        # Core logic and initialization class
├── render.php      # Entry point for the block renderer
├── ajax.php        # (Optional) AJAX handlers for the block
├── block-styles.css # CSS for the block
└── block-script.js  # JS for the block
```

## Core Implementation

### The `init` Class
Every block should have an `init.php` defining a class that extends `WicketAcc\Blocks`.

```php
namespace WicketAcc\Blocks\NewBlock;

use WicketAcc\Blocks;

class init extends Blocks
{
    public function __construct(
        protected array $block = [],
        protected bool $is_preview = false,
        protected ?Blocks $blocks = null
    ) {
        $this->block = $block;
        $this->is_preview = $is_preview;
        $this->blocks = $blocks ?? new Blocks();
        $this->render_block();
    }

    public function render_block()
    {
        // 1. Fetch ACF fields
        $title = get_field('field_name');

        // 2. Prepare arguments for the template
        $args = [
            'title' => $title,
            'attrs' => get_block_wrapper_attributes(),
        ];

        // 3. Render the template
        $this->blocks->render_template('new-block-template', $args);
    }
}
```

### Template Hierarchy
Templates are resolved in the following order:
1. **Theme**: `your-theme/templates-wicket/blocks/account-centre/{template-name}.php`
2. **Plugin**: `wicket-wp-account-centre/templates-wicket/blocks/account-centre/{template-name}.php`

## Best Practices

### 1. Naming Conventions
- **ACF Field Prefix**: Use a unique 3-5 character prefix for all fields (e.g., `nb_` for New Block) to avoid collisions in the database.
- **Template Slugs**: Use kebab-case for template filenames.

### 2. Styling
- Always use Wicket theme variables from `theme-variables.css`.
- Prefer TailwindCSS utility classes in your templates for layout.
- Use `get_block_wrapper_attributes()` to support standard Gutenberg alignment and spacing settings.

### 3. Dynamic Interaction
- Use **Datastar** (`data-star="..."`) for real-time UI updates.
- If using standard AJAX, register handlers in `ajax.php`.

### 4. Security
- Use `wp_nonce_field()` in forms.
- Sanitize all `$_GET` and `$_POST` data using WordPress standard functions.
- Perform capability checks before displaying sensitive information.

## Creating a New Block (Quick Start)
1. Create a new folder in `includes/blocks/`.
2. Copy `block.json`, `init.php`, and `render.php` from `_ac-base-block`.
3. Update the `name` and `title` in `block.json`.
4. Update the namespace in `init.php`.
5. Create a corresponding template file in `templates-wicket/blocks/account-centre/`.
6. Add your ACF field group in the WP Admin and export the JSON to `includes/acf-json/`."
