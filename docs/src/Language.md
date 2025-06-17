# ACC Language Class Documentation

## Overview
The `Language` class is responsible for loading the plugin's text domain, which enables translations for the Wicket Account Centre plugin. It ensures that the correct language files (`.mo`/`.po`) are loaded, allowing WordPress's localization functions (e.g., `__`, `_e`) to work correctly.

## Class Definition
```php
namespace WicketAcc;

class Language extends WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct();

    /**
     * Load text domain.
     */
    public function load_textdomain();
}
```

## Core Functionality
The class hooks into the `plugins_loaded` action to call its `load_textdomain` method. This method uses the `load_plugin_textdomain` WordPress function to load the translation files from the `languages` directory of the plugin. Importantly, this action is performed only if `!is_admin()` is true, meaning the text domain is primarily loaded for front-end translations.

## Usage
The `Language` class is instantiated in the main plugin file and works automatically. There is no need to interact with it directly. Its purpose is to make strings in the plugin translatable.

**Example of a translatable string in the plugin:**
```php
// This string can be translated via .po files.
echo __('Hello World', 'wicket-acc');
```

## Error Handling
The class does not perform explicit error handling. If the language files are missing, the strings will simply appear in their default (English) form.
