# Helper Functions Documentation

## Global Helper Function: WACC()

### Overview
The `WACC()` function serves as a global singleton accessor for plugin functionality. It provides a single entry point to access all helper methods and registered classes throughout the plugin.

### Implementation
```php
/**
 * Magic wrapper Class for WACC() helpers.
 *
 * @return MethodRouter Singleton instance
 */
function WACC()
{
    static $instance = null;

    if ($instance === null) {
        $instance = new MethodRouter();
    }

    return $instance;
}
```

### Usage Guidelines

1. **Direct Method Access**
   ```php
   // Access helper methods directly
   WACC()->method_name();
   ```

2. **Class Method Access**
   ```php
   // Access methods from registered classes
   WACC()->className->method_name();
   ```

### Architecture Notes

1. **Singleton Pattern**
   - Single instance maintained across all calls
   - Lazy initialization on first use
   - Static instance storage

2. **Method Routing**
   - Utilizes MethodRouter class
   - Provides access to registered classes
   - Maintains consistent access patterns

### Extension Guidelines

1. **Adding New Functionality**
   - DO NOT add new helpers to `helpers.php`
   - Use `class-wicket-acc-helpers.php` for new helper methods
   - Register classes through `class-acc-helpers-router.php`

2. **Class Registration**
   ```php
   // Example class registration in router
   class YourHelper {
       public function your_method() {
           // Method implementation
       }
   }

   // Access in code
   WACC()->yourHelper->your_method();
   ```

### Best Practices

1. **Code Organization**
   - Keep helper methods in appropriate classes
   - Use meaningful class and method names
   - Follow WordPress coding standards

2. **Method Implementation**
   - Keep methods focused and single-purpose
   - Use proper error handling
   - Document method parameters and returns

3. **Security Considerations**
   - Implement proper access controls
   - Validate and sanitize inputs
   - Follow WordPress security best practices

### Common Helper Classes

1. **Mdp**
   - API integration methods
   - Data synchronization
   - Error handling

2. **Router**
   - URL handling
   - Endpoint management
   - Request routing

3. **Blocks**
   - Block registration
   - Template handling
   - Block rendering

4. **User**
   - User management
   - Profile handling
   - Permission checks

### Integration Points

1. **WordPress Core**
   - Action and filter hooks
   - Database interactions
   - User management

2. **WooCommerce**
   - Order processing
   - Account management
   - Endpoint integration

3. **External Services**
   - MDP API integration
   - Third-party services
   - Data synchronization

### Error Handling

1. **Common Patterns**
   - Use WordPress error objects
   - Implement proper logging
   - Provide meaningful error messages

2. **Security**
   - Validate user capabilities
   - Sanitize inputs and outputs
   - Implement nonce checks

### Development Notes

1. **Adding New Helpers**
   ```php
   // In class-wicket-acc-helpers.php
   class Helpers {
       public function new_helper_method() {
           // Implementation
       }
   }

   // Usage
   WACC()->new_helper_method();
   ```

2. **Exposing Class Methods**
   ```php
   // In class-acc-helpers-router.php
   class HelperRouter {
       public function register_class() {
           // Registration logic
       }
   }
   ```

3. **Deprecation Process**
   - Mark deprecated methods with `@deprecated`
   - Provide migration path
   - Maintain backward compatibility

