# ACC WooCommerce Class Documentation

## Overview
The `WooCommerce` class manages the integration between the Account Centre and WooCommerce. It handles account endpoints, order management, and customer data synchronization.

## Class Definition
```php
namespace WicketAcc;

class WooCommerce {
    /**
     * WooCommerce endpoint configurations
     */
    protected const ENDPOINTS = [
        'orders' => [
            'slug' => 'orders',
            'title' => 'Orders',
            'position' => 20
        ],
        'subscriptions' => [
            'slug' => 'subscriptions',
            'title' => 'Subscriptions',
            'position' => 25
        ],
        'payment-methods' => [
            'slug' => 'payment-methods',
            'title' => 'Payment Methods',
            'position' => 30
        ]
    ];

    /**
     * Constructor.
     * Sets up WooCommerce integration
     */
    public function __construct() {
        add_filter('woocommerce_account_menu_items', [$this, 'modify_account_menu']);
        add_filter('woocommerce_get_endpoint_url', [$this, 'modify_endpoint_urls'], 10, 4);
        add_action('template_redirect', [$this, 'handle_endpoints']);
    }
}
```

## Core Methods

### Endpoint Management
```php
/**
 * Modifies WooCommerce account menu
 * Customizes menu items and order
 *
 * @param array $items Menu items
 * @return array Modified items
 */
public function modify_account_menu(array $items): array;

/**
 * Customizes endpoint URLs
 * Handles multilingual support
 *
 * @param string $url Endpoint URL
 * @param string $endpoint Endpoint name
 * @param string $value Query value
 * @param string $permalink Base permalink
 * @return string Modified URL
 */
public function modify_endpoint_urls(string $url, string $endpoint, string $value, string $permalink): string;
```

### Order Management
```php
/**
 * Synchronizes order with MDP
 * Updates membership and customer data
 *
 * @param int $order_id WooCommerce order ID
 * @return void
 */
protected function sync_order(int $order_id): void;

/**
 * Processes subscription changes
 * Updates membership status
 *
 * @param int $subscription_id WooCommerce subscription ID
 * @return void
 */
protected function process_subscription_change(int $subscription_id): void;
```

### Customer Integration
```php
/**
 * Links WooCommerce customer with MDP person
 *
 * @param int $customer_id WooCommerce customer ID
 * @param string $person_uuid MDP person UUID
 * @return bool Success status
 */
protected function link_customer(int $customer_id, string $person_uuid): bool;

/**
 * Synchronizes customer meta
 * Updates billing and shipping info
 *
 * @param int $customer_id WooCommerce customer ID
 * @param array $meta_data Meta data to sync
 * @return bool Success status
 */
protected function sync_customer_meta(int $customer_id, array $meta_data): bool;
```

## Features

### Template Management
```php
/**
 * Template override priorities
 */
protected const TEMPLATE_HIERARCHY = [
    'user' => 10,    // User custom templates
    'theme' => 20,   // Theme templates
    'plugin' => 30,  // Plugin default templates
    'wc' => 40      // WooCommerce templates
];
```

### Account Navigation
- Custom menu structure
- Endpoint management
- URL customization
- Access control

### Order Processing
- Order synchronization
- Subscription handling
- Payment processing
- Status updates

### Error Handling
- Invalid endpoints
- Order sync failures
- Customer link errors
- Template loading issues
- Payment processing errors
