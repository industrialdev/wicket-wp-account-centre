# ACC Helpers Class Documentation

## Overview
The `Helpers` class provides a collection of utility methods and helper functions used throughout the Wicket Account Centre plugin. These methods offer commonly used functionality such as data formatting, string manipulation, and other helper tasks.

## Class Definition
```php
namespace WicketAcc;

class Helpers {
    /**
     * Constructor.
     * Initializes the helper class.
     */
    public function __construct() {}
}
```

## Core Methods

### format_date
```php
/**
 * Formats a date string using a specified format.
 *
 * @param string $date The date string to format.
 * @param string $format The desired date format.
 * @return string The formatted date string.
 */
public function format_date(string $date, string $format = 'F j, Y'): string;
```

### format_phone
```php
/**
 * Formats a phone number string.
 *
 * @param string $phone The phone number string to format.
 * @return string The formatted phone number string.
 */
public function format_phone(string $phone): string;
```

### sanitize_text
```php
/**
 * Sanitizes a text string.
 *
 * @param string $text The text string to sanitize.
 * @return string The sanitized text string.
 */
public function sanitize_text(string $text): string;
```

### get_countries
```php
/**
 * Retrieves a list of countries.
 *
 * @return array An array of countries.
 */
public function get_countries(): array;
```

### get_states_provinces
```php
/**
 * Retrieves a list of states/provinces for a given country.
 *
 * @param string $country_code The country code to retrieve states/provinces for.
 * @return array An array of states/provinces.
 */
public function get_states_provinces(string $country_code): array;
```

### is_valid_uuid
```php
/**
 * Checks if a string is a valid UUID.
 *
 * @param string $uuid The string to check.
 * @return bool True if the string is a valid UUID, false otherwise.
 */
public function is_valid_uuid(string $uuid): bool;
```

## Features & Usage

- **Data Formatting:** Provides methods for formatting dates and phone numbers.
- **Sanitization:** Offers a method for sanitizing text strings.
- **Location Data:** Provides methods for retrieving lists of countries and states/provinces.
- **Validation:** Offers a method for validating UUIDs.

## Usage Example

```php
// Accessing the Helpers class through the WACC() function
$formatted_date = WACC()->format_date('2024-01-01', 'm/d/Y');
```

## Error Handling
- The methods in this class handle input validation and return appropriate values or throw exceptions when necessary.
