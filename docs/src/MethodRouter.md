# MethodRouter Class Documentation

## Overview
The `MethodRouter` class is a "magic" wrapper that provides centralized access to the plugin's core components and helper functions. It is exposed through the global `WACC()` function, which returns an instance of `MethodRouter`. This allows for a simple and consistent way to interact with different parts of the plugin.

## Class Definition
```php
namespace WicketAcc;

class MethodRouter
{
    private array $instances = [];
    private Helpers $helpersInstance;

    /**
     * Constructor.
     * Initializes the helpersInstance and populates the instances array
     * with singleton instances of the core components.
     */
    public function __construct();

    /**
     * Get the instance of a registered component.
     *
     * @param string $name Class name of the component.
     * @return Blocks|MdpApi|OrganizationProfile|Profile|User The component instance.
     * @throws \Exception If the component is not registered.
     */
    public function __get(string $name): Blocks|MdpApi|OrganizationProfile|Profile|User;

    /**
     * Call a method on the Helpers instance or get a component instance.
     *
     * @param string $name Method name (for Helpers) or Class name (for component).
     * @param array $arguments Arguments for the method call.
     * @return object|mixed Component instance or result of the helper method call.
     * @throws \Exception If the method or component is not found.
     */
    public function __call(string $name, array $arguments): object|mixed;

    /**
     * Statically call a method on the Helpers instance.
     *
     * @param string $name Method name.
     * @param array $arguments Arguments for the method call.
     * @return mixed Result of the helper method call.
     * @throws \Exception If the method is not found on Helpers.
     */
    public static function __callStatic(string $name, array $arguments): mixed;
}
```

## Registered Components
The `MethodRouter` provides access to the following components:

- **`Helpers`**: A collection of utility methods.
- **`MdpApi`**: Manages communication with the Wicket Member Data Platform (MDP) API.
- **`Profile`**: Manages user profile pictures and avatars.
- **`OrganizationProfile`**: Manages organization logos.
- **`Blocks`**: Handles custom Gutenberg blocks.
- **`User`**: Manages user-related data and operations.

Note: While methods of the `Helpers` class are accessed directly (e.g., `WACC()->someHelper()`), the `Helpers` instance itself is not retrievable via property access (e.g., `WACC()->Helpers`) as it's stored separately and not part of the `$instances` array managed by `__get`.

## Core Functionality & Usage
The `MethodRouter` uses PHP's magic methods (`__get`, `__call`) to provide a flexible API.

### 1. Accessing Component Instances
You can get a singleton instance of any registered component (except `Helpers`) in two ways:

**A) Property Access (`__get`)**
This is the recommended approach. Access the component as if it were a property on the `WACC()` object. The property name must match the class name exactly (case-sensitive).

```php
// Get the Profile component instance
$profile_manager = WACC()->Profile;

// Now use its methods
$avatar_url = $profile_manager->get_profile_picture();
```

**B) Method Access (`__call`)**
Alternatively, you can access the component by calling a method with the same name as the class.

```php
// Get the User component instance
$user_manager = WACC()->User();

// Now use its methods
// (Assuming a method 'get_user_roles' exists on the User class)
// $roles = $user_manager->get_user_roles();
```

### 2. Calling Helper Methods
Methods from the `Helpers` class can be called directly on the `WACC()` object. Method names in PHP are case-insensitive.

```php
// Call the 'getAccSlug' method from the Helpers class
$slug = WACC()->getAccSlug();

if ($slug) {
    // Build a URL, for example
    $profile_url = home_url('/' . $slug . '/profile');
    echo "Your profile URL is: " . esc_url($profile_url);
}

// Get the name of the Account Centre page
$page_name = WACC()->getAccName();
```

### 3. Calling Helper Methods Statically (`__callStatic`)
Helper methods can also be called statically on the `WACC` "class" itself. This internally creates a `MethodRouter` instance and routes the call to the `Helpers` instance.

```php
// Statically call the 'getAccSlug' method from the Helpers class
$slug = WACC::getAccSlug();

if ($slug) {
    echo "Account Centre Slug (static call): " . esc_html($slug);
}
```

## Error Handling
- Throws an `\Exception` if you try to access a component instance that is not registered (e.g., `WACC()->NonExistentClass`).
- Throws an `\Exception` if you call a method that does not exist on the `Helpers` class and is not a registered component name (e.g., `WACC()->undefinedHelperMethod()`). The error message will be similar to: `Method or class instance 'undefinedHelperMethod' does not exist. Available instances: MdpApi, Profile, OrganizationProfile, Blocks, User`.
