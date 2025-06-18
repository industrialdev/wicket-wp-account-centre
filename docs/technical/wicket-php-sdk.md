# Wicket PHP SDK Integration

## Overview

The Wicket Account Centre plugin integrates with the `industrialdev/wicket-sdk-php` package to provide seamless communication with the Wicket API. This SDK offers a comprehensive set of tools for managing users, organizations, orders, and other Wicket entities.

## Installation

The SDK is installed via Composer. Previously, the plugin used Strauss to prefix the Wicket SDK namespaces. This is no longer the case, and the SDK is used with its original namespaces.

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:industrialdev/wicket-sdk-php.git",
            "no-api": true
        }
    ],
    "require": {
        "industrialdev/wicket-sdk-php": "dev-master"
    }
}
```

## SDK Namespace

The Wicket PHP SDK uses its original `Wicket` namespace (e.g., `\Wicket\Client`, `\Wicket\Entities\People`). Ensure your `use` statements and class instantiations reflect this (e.g., `use Wicket\Client;`, `new \Wicket\Client(...);`).

## Available Classes

### Core Classes

- **`Wicket\Client`** - Main SDK client for API communication
- **`Wicket\ApiResource`** - Base class for API resources
- **`Wicket\ResponseHelper`** - Helper for processing API responses
- **`Wicket\ResponsePager`** - Pagination helper for large datasets
- **`Wicket\WicketCollection`** - Collection wrapper for multiple entities

### Entity Classes

- **`Wicket\Entities\People`** - User/person management
- **`Wicket\Entities\Organizations`** - Organization management
- **`Wicket\Entities\Orders`** - Order processing
- **`Wicket\Entities\Memberships`** - Membership handling
- **`Wicket\Entities\Addresses`** - Address management
- **`Wicket\Entities\Emails`** - Email management
- **`Wicket\Entities\Phones`** - Phone number management
- **`Wicket\Entities\WebAddresses`** - Web address management
- **`Wicket\Entities\Connections`** - Connection management
- **`Wicket\Entities\Roles`** - Role management
- **`Wicket\Entities\ResourceTypes`** - Resource type management
- **`Wicket\Entities\Variants`** - Variant management
- **`Wicket\Entities\Intervals`** - Interval management

## Basic Usage

### Initializing the Client

```php
<?php

// Ensure you are in the correct namespace or use fully qualified class names.
// Example for using the SDK:

use Wicket\Client;

// Initialize the Wicket SDK client
$client = new Client([
    'base_uri' => 'https://api.wicket.io/',
    'api_key' => get_option('wicket_api_key'),
    'secret_key' => get_option('wicket_secret_key'),
]);
```

### Working with People (Users)

```php
use Wicket\Entities\People;

// Get a person by ID
$people = new People($client);
$person = $people->fetch($person_id);

// Create a new person
$new_person = $people->create([
    'given_name' => 'John',
    'family_name' => 'Doe',
    'email' => 'john.doe@example.com'
]);

// Update a person
$updated_person = $people->update($person_id, [
    'given_name' => 'Jane'
]);
```

### Working with Organizations

```php
use Wicket\Entities\Organizations;

// Get an organization by ID
$organizations = new Organizations($client);
$organization = $organizations->fetch($org_id);

// Create a new organization
$new_org = $organizations->create([
    'legal_name' => 'Example Corp',
    'description' => 'A sample organization'
]);
```

### Working with Orders

```php
use Wicket\Entities\Orders;

// Get orders for a person
$orders = new Orders($client);
$person_orders = $orders->fetchAll(['person_id' => $person_id]);

// Get a specific order
$order = $orders->fetch($order_id);
```

## Error Handling

```php
try {
    $person = $people->fetch($person_id);
} catch (\Exception $e) {
    error_log('Wicket SDK Error: ' . $e->getMessage());
    // Handle error appropriately
}
```

## Configuration

### Required Settings

The following WordPress options should be configured for the SDK to work:

- `wicket_api_key` - Your Wicket API key
- `wicket_secret_key` - Your Wicket secret key
- `wicket_api_base_url` - Base URL for the Wicket API (default: https://api.wicket.io/)

### Setting Configuration

```php
// Set API credentials
update_option('wicket_api_key', 'your-api-key');
update_option('wicket_secret_key', 'your-secret-key');
update_option('wicket_api_base_url', 'https://api.wicket.io/');
```

## Integration Examples

### Syncing WordPress User with Wicket Person

```php
function sync_wp_user_to_wicket($user_id) {
    $wp_user = get_user_by('ID', $user_id);
    
    $client = new Wicket\Client([
        'base_uri' => get_option('wicket_api_base_url', 'https://api.wicket.io/'),
        'api_key' => get_option('wicket_api_key'),
        'secret_key' => get_option('wicket_secret_key'),
    ]);
    
    $people = new Wicket\Entities\People($client);
    
    try {
        $person_data = [
            'given_name' => $wp_user->first_name,
            'family_name' => $wp_user->last_name,
            'email' => $wp_user->user_email,
        ];
        
        $wicket_person_id = get_user_meta($user_id, 'wicket_person_id', true);
        
        if ($wicket_person_id) {
            // Update existing person
            $person = $people->update($wicket_person_id, $person_data);
        } else {
            // Create new person
            $person = $people->create($person_data);
            update_user_meta($user_id, 'wicket_person_id', $person['id']);
        }
        
        return $person;
    } catch (\Exception $e) {
        error_log('Failed to sync user to Wicket: ' . $e->getMessage());
        return false;
    }
}
```

### Fetching User Organizations

```php
function get_user_organizations($user_id) {
    $wicket_person_id = get_user_meta($user_id, 'wicket_person_id', true);
    
    if (!$wicket_person_id) {
        return [];
    }
    
    $client = new Wicket\Client([
        'base_uri' => get_option('wicket_api_base_url', 'https://api.wicket.io/'),
        'api_key' => get_option('wicket_api_key'),
        'secret_key' => get_option('wicket_secret_key'),
    ]);
    
    $organizations = new Wicket\Entities\Organizations($client);
    
    try {
        return $organizations->fetchAll(['person_id' => $wicket_person_id]);
    } catch (\Exception $e) {
        error_log('Failed to fetch user organizations: ' . $e->getMessage());
        return [];
    }
}
```

## Best Practices

### 1. Caching

Cache API responses to reduce API calls:

```php
function get_cached_wicket_person($person_id) {
    $cache_key = "wicket_person_{$person_id}";
    $cached = wp_cache_get($cache_key);
    
    if ($cached !== false) {
        return $cached;
    }
    
    $client = new Wicket\Client(/* config */);
    $people = new Wicket\Entities\People($client);
    
    try {
        $person = $people->fetch($person_id);
        wp_cache_set($cache_key, $person, '', 300); // Cache for 5 minutes
        return $person;
    } catch (\Exception $e) {
        return false;
    }
}
```

### 2. Rate Limiting

Implement rate limiting to avoid API limits:

```php
function rate_limited_api_call($callback) {
    $last_call = get_transient('wicket_last_api_call');
    $min_interval = 1; // 1 second between calls
    
    if ($last_call && (time() - $last_call) < $min_interval) {
        sleep($min_interval - (time() - $last_call));
    }
    
    $result = $callback();
    set_transient('wicket_last_api_call', time(), 60);
    
    return $result;
}
```

### 3. Error Logging

Log all API errors for debugging:

```php
function log_wicket_error($message, $context = []) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf(
            'Wicket SDK Error: %s | Context: %s',
            $message,
            json_encode($context)
        ));
    }
}
```

## Troubleshooting

### Common Issues

1. **Authentication Errors**: Verify API key and secret key are correct
2. **Network Timeouts**: Check network connectivity and API endpoint availability
3. **Rate Limiting**: Implement proper rate limiting and retry logic
4. **Data Validation**: Ensure data sent to API meets Wicket's requirements

### Debug Mode

Enable debug mode to see detailed API requests and responses:

```php
$client = new Wicket\Client([
    'base_uri' => get_option('wicket_api_base_url'),
    'api_key' => get_option('wicket_api_key'),
    'secret_key' => get_option('wicket_secret_key'),
    'debug' => true, // Enable debug mode
]);
```

## Repository Information

- **Repository**: `git@github.com:industrialdev/wicket-sdk-php.git`
- **Branch**: `master`
- **Installation Method**: Composer
- **SDK Namespace**: `Wicket\` (The SDK's original namespace is used; it is not prefixed by Strauss within this plugin.)

## Version Information

The SDK version can be checked using:

```php
use Wicket\Version;

echo Version::get(); // Returns the current SDK version
```
