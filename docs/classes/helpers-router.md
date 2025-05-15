# ACC Helpers Router Class Documentation

## Overview
The `Helpers_Router` class provides a centralized mechanism for registering and routing helper functions and classes within the Wicket Account Centre plugin. It facilitates dynamic method resolution and simplifies accessing helper functionality across the plugin.

## Class Definition
```php
namespace WicketAcc;

class Helpers_Router {
    /**
     * Constructor.
     * Initializes internal storage for registered helpers.
     */
    public function __construct();
}
```

## Core Methods

### register_helper
```php
/**
 * Registers a helper instance under a given key.
 *
 * @param string $name The unique key for the helper.
 * @param mixed $instance The helper object instance.
 * @return void
 */
public function register_helper(string $name, $instance): void;
```

### get_helper
```php
/**
 * Retrieves a registered helper by key.
 *
 * @param string $name The key for the helper.
 * @return mixed The helper instance if registered; null otherwise.
 */
public function get_helper(string $name);
```

### route_method
```php
/**
 * Routes a dynamic method call to a registered helper.
 * Checks if the helper has the method, then calls it with provided arguments.
 *
 * @param string $helper_name The key for the helper.
 * @param string $method The method name to route.
 * @param array $arguments An array of arguments for the method.
 * @return mixed The return value from the helper method.
 */
public function route_method(string $helper_name, string $method, array $arguments = []);
```

### is_registered
```php
/**
 * Checks if a helper is registered.
 *
 * @param string $name The key for the helper.
 * @return bool True if the helper is registered; false otherwise.
 */
public function is_registered(string $name): bool;
```

## Features & Usage

- **Dynamic Resolution:** Allows calling methods on helpers without direct instantiation.
- **Centralized Registration:** Store and manage all helper instances in one place.
- **Extendability:** Facilitates adding or replacing helper functionality as needed.

## Usage Example

```php
// Registering a helper
$helpers_router = new Helpers_Router();
$myHelper = new My_Helper_Class();
$helpers_router->register_helper('my_helper', $myHelper);

// Invoking a method through the router
if ($helpers_router->is_registered('my_helper')) {
    $result = $helpers_router->route_method('my_helper', 'do_something', ['param1', 'param2']);
    // Process $result as needed.
}
```

## Error Handling
- Returns `null` if a requested helper isn’t registered.
- Throws an error if the helper exists but the method does not.
