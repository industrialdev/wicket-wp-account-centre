# ACC Plugin Entrypoint Documentation

## Overview
The `class-wicket-acc-main.php` file serves as the main entrypoint for the Wicket Account Centre WordPress plugin. This plugin customizes WooCommerce my-account features and expands it with additional blocks and pages.

## Plugin Information
- **Name**: Wicket Account Centre
- **Dependency**: WooCommerce
- **Namespace**: WicketAcc

## Core Constants
```php
WICKET_ACC_VERSION          // Plugin version from file header
WICKET_ACC_PATH             // Plugin directory path
WICKET_ACC_URL              // Plugin directory URL
WICKET_ACC_BASENAME         // Plugin base name
WICKET_ACC_UPLOADS_PATH     // Upload directory path (/wicket-account-center/)
WICKET_ACC_UPLOADS_URL      // Upload directory URL (/wicket-account-center/)
WICKET_ACC_PLUGIN_TEMPLATE_PATH // Plugin templates directory (/templates-wicket/)
WICKET_ACC_PLUGIN_TEMPLATE_URL  // Plugin templates URL (/templates-wicket/)
WICKET_ACC_USER_TEMPLATE_PATH   // Theme templates directory (/templates-wicket/)
WICKET_ACC_USER_TEMPLATE_URL    // Theme templates URL (/templates-wicket/)
WICKET_ACC_TEMPLATES_FOLDER     // Templates folder name (account-centre)
```

## Protected Properties

### Account Index Slugs
Multilingual slugs for the main account page:
```php
$acc_index_slugs = [
    'en' => 'my-account',
    'fr' => 'mon-compte',
    'es' => 'mi-cuenta'
];
```

### WooCommerce Index Slugs
Multilingual slugs for WooCommerce account pages:
```php
$acc_wc_index_slugs = [
    'en' => 'my-account',
    'fr' => 'my-compte',
    'es' => 'my-cuenta'
];
```

### Available Pages
The plugin defines several types of pages:

1. **Wicket Pages**
   - Edit Profile
   - My Events
   - My Jobs
   - Post a Job
   - Change Password
   - Organization Management
   - Organization Profile
   - Organization Members
   - Organization Roster
   - Global Header-Banner

2. **WooCommerce Endpoints**
   - Add Payment Method
   - Set Default Payment Method
   - Orders
   - View Order
   - Downloads
   - Edit Account
   - Edit Address
   - Payment Methods
   - Subscriptions

### Auto-Created Pages
The following pages are automatically created during plugin activation:
```php
[
    'edit-profile',
    'change-password',
    'organization-management',
    'organization-profile',
    'organization-members',
    'organization-roster',
    'acc_global-headerbanner',
    'add-payment-method',
    'set-default-payment-method',
    'orders',
    'view-order',
    'downloads',
    'payment-methods',
    'subscriptions'
]
```

### WooCommerce Endpoints
The plugin provides multilingual support for WooCommerce endpoints:

```php
$acc_wc_endpoints = [
    'order-pay' => [
        'en' => 'order-pay',
        'fr' => 'ordre-paiement',
        'es' => 'orden-pago'
    ],
    'order-received' => [
        'en' => 'order-received',
        'fr' => 'ordre-recibida',
        'es' => 'orden-recibida'
    ],
    // ... and more
];
```

### Preferred WooCommerce Endpoints
Endpoints that should use WooCommerce's implementation:
```php
$acc_prefer_wc_endpoints = [
    'add-payment-method',
    'payment-methods'
];
```

## Core Classes
The plugin initializes the following classes in its `run()` method:

1. **Base Classes**
   - `Mdp`: MDP API integration
   - `Router`: URL handling
   - `Blocks`: Block management
   - `Helpers`: Utility functions
   - `Registers`: WordPress registrations

2. **Feature Classes**
   - `Profile`: User profile management
   - `OrganizationProfile`: Organization management
   - `Assets`: Asset management
   - `WooCommerce`: WooCommerce integration
   - `User`: User management

3. **Conditional Classes**
   - `AdminSettings`: Admin-only functionality
   - `Language`: Front-end language handling

## File Structure
The plugin organizes its files into three categories:

1. **Admin Files** (`$includes_admin`)
   - `classes/admin/class-wicket-acc-admin.php`

2. **Core Classes** (`$include_classes`)
   - Language
   - MDP API
   - Registers
   - Blocks
   - Profile
   - Organization Profile
   - User
   - Router
   - WooCommerce
   - Helpers
   - Router Helpers
   - Assets

3. **Global Files** (`$includes_global`)
   - `includes/helpers.php`
   - `includes/deprecated.php`

## Initialization Flow
1. Check for WooCommerce dependency
   - Deactivates plugin if WooCommerce is not active
   - Shows admin notice to install WooCommerce
2. Define constants
3. Add filter for job manager pages
4. Register activation hook
5. Include required files based on context
6. Initialize admin settings if in admin area
7. Initialize core classes
8. Initialize language class if not in admin

## Multilingual Support
Built-in support for:
- English (en)
- French (fr)
- Spanish (es)

All page slugs, endpoints, and URLs are localized for these languages.
