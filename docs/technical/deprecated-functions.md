# Deprecated Functions Documentation

## Overview
This document lists all deprecated functions in the Wicket Account Centre plugin. All functions listed here are pending reimplementation as methods in their respective classes.

## Membership Functions

### wicket_get_active_memberships
```php
/**
 * Returns active memberships from wicket API.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param string $iso_code (Optional) ISO code for the language: en, fr, es, etc.
 * @return array $memberships slug and id
 */
function wicket_get_active_memberships($iso_code = 'en')
```

### woo_get_active_memberships
```php
/**
 * Returns active memberships from WooCommerce.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @return array $memberships slug and id
 */
function woo_get_active_memberships()
```

### wicket_get_active_memberships_relationship
```php
/**
 * Returns active memberships relationship from wicket API.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @return array $memberships relationship
 */
function wicket_get_active_memberships_relationship($org_uuid)
```

## Profile Functions

### wicket_profile_widget_validation
```php
/**
 * Validate profile widget fields.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param array $fields Fields to validate
 * @return bool
 */
function wicket_profile_widget_validation($fields = [])
```

### wicket_validation_addresses
```php
/**
 * Validates the addresses of the person.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param object $person Person object
 * @return bool $addresses
 */
function wicket_validation_addresses($person)
```

## Menu Walker Classes

### wicket_acc_menu_walker
```php
/**
 * Menu walker for the wicket ACC menu.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @return void
 */
class wicket_acc_menu_walker extends Walker_Nav_Menu
```

### wicket_acc_menu_mobile_walker
```php
/**
 * Menu walker (mobile) for the wicket ACC menu.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @return void
 */
class wicket_acc_menu_mobile_walker extends Walker_Nav_Menu
```

## Cart Functions

### wicket_ac_maybe_add_multiple_products_to_cart
```php
/**
 * Add multiple products to cart.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @return void
 */
function wicket_ac_maybe_add_multiple_products_to_cart()
```

## Renewal Functions

### wicket_ac_memberships_get_product_link_data
```php
/**
 * Returns productlinks for renewal callouts based on the next tier's products assigned.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param mixed $membership
 * @param mixed $renewal_type
 * @return string[][][]
 */
function wicket_ac_memberships_get_product_link_data($membership, $renewal_type)
```

### wicket_ac_memberships_get_page_link_data
```php
/**
 * Get page link data for memberships.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param mixed $membership
 * @return array
 */
function wicket_ac_memberships_get_page_link_data($membership)
```

## Page Management Functions

### wicket_acc_alter_wp_job_manager_pages
```php
/**
 * Alter the 'pages' admin settings to provide the wicket ACC pages along with normal pages.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param string $output
 * @param array $parsed_args
 * @param array $pages
 * @return string $output
 */
function wicket_acc_alter_wp_job_manager_pages($output, $parsed_args, $pages)
```

### wp_job_dropdown_pages
```php
/**
 * Dropdown pages for the wicket ACC Job Manager.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param array $parsed_args
 * @return string $output
 */
function wp_job_dropdown_pages($parsed_args = '')
```

## Avatar Functions

### wicket_acc_get_avatar
```php
/**
 * Get user profile picture
 * To pull in as wp_user profile image.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param mixed $user WP_User object, user ID or email
 * @param array $args (Optional)
 * @return string URL to the profile picture
 */
function wicket_acc_get_avatar($user, $args = [])
```

## Helper Functions

### is_renewal_period
```php
/**
 * Check if membership is in renewal period.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param array $memberships
 * @param string $renewal_period
 * @return array
 */
function is_renewal_period($memberships, $renewal_period)
```

## Migration Notes
All functions listed here are scheduled for reimplementation as methods in their respective classes. The functions will remain available but deprecated until the reimplementation is complete.
