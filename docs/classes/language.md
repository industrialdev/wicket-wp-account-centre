# ACC Language Class Documentation

## Overview
The `Language` class manages multilingual functionality within the Wicket Account Centre plugin. It handles language switching, translation loading, and URL management for different locales.

## Class Definition
```php
namespace WicketAcc;

class Language {
    /**
     * Supported languages configuration
     */
    protected array $supported_languages = [
        'en' => [
            'name' => 'English',
            'locale' => 'en_US',
            'flag' => '🇺🇸'
        ],
        'fr' => [
            'name' => 'Français',
            'locale' => 'fr_FR',
            'flag' => '🇫🇷'
        ],
        'es' => [
            'name' => 'Español',
            'locale' => 'es_ES',
            'flag' => '🇪🇸'
        ]
    ];

    /**
     * Constructor.
     * Sets up language hooks and filters.
     */
    public function __construct();
}
```

## Core Methods

### Language Management
```php
/**
 * Gets current language code
 * Defaults to 'en' if not set
 *
 * @return string Current language code
 */
public function get_current_language(): string;

/**
 * Sets the active language
 * Updates WordPress locale
 *
 * @param string $lang_code Language code to set
 * @return bool Success status
 */
public function set_language(string $lang_code): bool;

/**
 * Gets list of available languages
 *
 * @return array Available languages with metadata
 */
public function get_available_languages(): array;
```

### URL Management
```php
/**
 * Modifies URLs to include language code
 * Handles both pretty and plain permalinks
 *
 * @param string $url URL to modify
 * @param string $lang_code Language code to add
 * @return string Modified URL
 */
public function add_language_to_url(string $url, string $lang_code): string;

/**
 * Gets language from current URL
 * Extracts language code from URL path
 *
 * @return string|null Language code if found
 */
public function get_language_from_url(): ?string;
```

### Translation Loading
```php
/**
 * Loads language files
 * Handles MO/PO file loading
 *
 * @param string $domain Text domain
 * @return bool Success status
 */
public function load_language_files(string $domain): bool;
```

## Features

### Language Support
- Multiple language configurations
- Language switching
- URL management
- Translation loading
- Locale handling

### Integration Points
```php
/**
 * WordPress Filters
 */
add_filter('locale', [$this, 'filter_locale']);
add_filter('pre_option_WPLANG', [$this, 'filter_default_language']);
add_filter('language_attributes', [$this, 'filter_language_attributes']);
```

## Usage Examples

### Language Switching
```php
$language = new Language();
$language->set_language('fr');
```

### URL Generation
```php
$language = new Language();
$url = $language->add_language_to_url('/my-account/', 'es');
```

## Error Handling
- Invalid language codes
- Missing translations
- URL parsing failures
- Locale setting errors
