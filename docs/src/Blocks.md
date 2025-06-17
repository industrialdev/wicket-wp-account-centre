# ACC Blocks Class Documentation

## Overview
The `Blocks` class is responsible for managing the registration and rendering of ACF (Advanced Custom Fields) blocks within the Wicket Account Centre plugin. It handles the discovery of blocks, registration via `block.json` files, ACF field group JSON loading and saving, custom block categories, and template rendering.

## Class Definition
```php
namespace WicketAcc;

use WP_Block_Type_Registry; // Added use statement

class Blocks extends WicketAcc
{
    private $current_group_being_saved;

    /**
     * Constructor.
     */
    public function __construct();

    /**
     * Add Wicket block categories for Gutenberg Editor.
     */
    public function editor_block_category($categories);

    /**
     * Load ACF Blocks
     * Automatically register blocks from the blocks folder
     * Also register block styles and scripts.
     */
    public function load_wicket_blocks();

    /**
     * Load ACF field groups.
     */
    public function load_acf_field_group($paths);

    /**
     * Get ACF Blocks from all folders included in the blocks folder.
     */
    public function get_wicket_blocks();

    /**
     * ACF field group update.
     */
    public function update_field_group($group);

    /**
     * Save ACF field group in plugin directory.
     */
    public function save_json_folder($path);

    /**
     * Get Block template path.
     */
    public function get_block_template_path($template_name);

    /**
     * Render Block template.
     */
    public function render_template($template_name = '', $args = []);
}
```

## Core Functionality

### Initialization (`__construct`)
The constructor sets up various WordPress hooks:
- `block_categories_all`: Calls `editor_block_category` to add custom block categories.
- `init` (priority 5): Calls `load_wicket_blocks` to register ACF blocks.
- `acf/settings/load_json`: Calls `load_acf_field_group` to add a path for ACF JSON files.
- `acf/update_field_group`: Calls `update_field_group` to intercept ACF field group saving.
- `acf/settings/save_json` (priority 100): Calls `save_json_folder` to specify where ACF JSON files for plugin blocks are saved.

### Block Category Registration (`editor_block_category`)
This method adds a custom block category to the Gutenberg editor:
- Slug: `wicket-account-center`
- Title: `Wicket_AC`

### Block Loading (`load_wicket_blocks`)
- Scans the `WICKET_ACC_PATH . 'includes/blocks/'` directory for block subdirectories (ignoring those starting with `_` or named `.` or `..`).
- For each valid block directory:
    - It first checks if the block (e.g., `wicket-ac/block-name`) is already registered using `WP_Block_Type_Registry::get_instance()->get_registered()`. If so, it skips to the next block.
    - It then checks for a `block.json` file.
    - If `block.json` exists, it registers the block using `register_block_type(PATH_TO_BLOCK_JSON)`.
        - *Note: The source code contains commented-out logic for manually registering block-specific `block-styles.css` and `block-script.js`. This was likely superseded by the expectation that `block.json` handles asset registration automatically.*
    - It also includes `init.php` (for server-side block logic) and `ajax.php` (for AJAX handlers) from the block's directory if they exist.

### ACF Field Group Management
- **`load_acf_field_group($paths)`**: Adds `WICKET_ACC_PATH . 'includes/acf-json'` to the array of paths where ACF looks for JSON field group definitions. This allows the plugin to bundle its ACF field groups.
- **`update_field_group($group)`**: When an ACF field group is saved, this method checks if the group's title starts with "ACC". If so, it stores the title in `$this->current_group_being_saved`. This is a preparatory step for `save_json_folder`.
- **`save_json_folder($path)`**: If `$this->current_group_being_saved` indicates an "ACC" prefixed field group is being saved, this method overrides the default ACF JSON save path to `WICKET_ACC_PATH . 'includes/acf-json'`. This ensures the plugin's block-specific field groups are saved within the plugin directory.

### Template Rendering
- **`get_block_template_path($template_name)`**: 
    - Sanitizes the `$template_name`.
    - First, it checks for the template in the child theme: `WICKET_ACC_USER_TEMPLATE_PATH . 'blocks/' . WICKET_ACC_TEMPLATES_FOLDER . '/' . $template_name . '.php'`.
    - If not found, it checks in the plugin's default template location: `WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'blocks/' . WICKET_ACC_TEMPLATES_FOLDER . '/' . $template_name . '.php'`.
    - Returns the path if found, otherwise `false`.
- **`render_template($template_name = '', $args = [])`**: 
    - If `$template_name` is empty or `get_block_template_path` returns `false`, it outputs an error message (`<p>Template ... not found</p>`) and returns.
    - Otherwise, it uses `wp_parse_args($args, [])` to ensure `$args` is an array and merges it with defaults, then includes the found template file, passing the `$args` array to it.

### Block Discovery (`get_wicket_blocks`)
- Scans the `WICKET_ACC_PATH . 'includes/blocks/` directory.
- Filters out `.` , `..`, `.DS_Store`, and any directory starting with an underscore (e.g., `_ac-base-block`).
- Returns an array of valid block directory names.

## Block Structure Expectation
Each block is expected to reside in its own subdirectory within `WICKET_ACC_PATH . 'includes/blocks/'`. A crucial file for each block is `block.json`, which is used for registration. Optional files include `init.php` (for server-side block logic) and `ajax.php` (for AJAX handlers related to the block).

## Usage
The `Blocks` class is typically instantiated once in the main plugin file. Its constructor handles the setup of all necessary hooks for block registration, ACF JSON integration, and template management.

**Example Instantiation:**
```php
// In the main plugin file or a relevant setup location
new \WicketAcc\Blocks();
```
