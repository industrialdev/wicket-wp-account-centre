---
title: "Wicket PHP SDK & MDP Integration"
audience: [developer, agent]
php_class: WicketAcc\WicketAcc
source_files: ["src/Mdp/", "src/Profile.php", "src/Helpers.php", "vendor/industrialdev/wicket-sdk-php/"]
---

# Wicket PHP SDK & MDP Integration

## Overview
The plugin utilizes the `industrialdev/wicket-sdk-php` to communicate with the Wicket Member Data Platform (MDP). While the SDK is available directly, the plugin provides a high-level abstraction layer via the `WACC()->Mdp()` service.

## Preferred Usage: `WACC()->Mdp()`
Instead of instantiating the SDK `Client` manually, you should use the specialized handlers provided by the plugin. These handlers manage authentication, caching, and error handling automatically.

### Basic Pattern
```php
// Access specialized MDP services
$personService = WACC()->Mdp()->Person();
$orgService    = WACC()->Mdp()->Organization();
$membership    = WACC()->Mdp()->Membership();
```

### Common Examples

#### 1. Fetching the Current Person
```php
$person = WACC()->Mdp()->Person()->getCurrentPerson();
$uuid   = WACC()->Mdp()->Person()->getCurrentPersonUuid();
```

#### 2. Managing Organizations
```php
// Get organizations where the user has a relationship
$orgs = WACC()->Mdp()->Organization()->getCurrentUserOrganizations();

// Fetch a specific organization
$org = WACC()->Mdp()->Organization()->fetch($org_uuid);
```

#### 3. Working with Touchpoints
```php
// Fetch touchpoints for a specific service (e.g., Moodle)
$touchpoints = WACC()->Mdp()->Touchpoint()->getCurrentUserTouchpoints($service_id);
```

## Technical Architecture

`WicketAcc\Mdp\Init` acts as a gateway. It uses magic methods to lazily instantiate sub-services in `src/Mdp/`:
- `Person.php`
- `Organization.php`
- `Membership.php`
- `Touchpoint.php`
- `Address.php`
- `Roles.php`

The OrgMan org-roster library talks to the same MDP through `WicketORM\Services\MembershipService`, `WicketORM\Services\OrganizationService`, `WicketORM\Services\PermissionService`, and the rest of the in-tree services. They do **not** go through `WACC()->Mdp()` — they hold their own SDK client. See [ARCHITECTURE.md](../ORM/engineering/ARCHITECTURE.md).

## SDK Client Initialization
If you need direct access to the SDK `Client` (e.g., for custom API calls not covered by existing services):

```php
$client = WACC()->Mdp()->initClient();

if ($client) {
    // Perform direct SDK calls
    $response = $client->get('custom/endpoint');
}
```

## Error Handling & Logging
The MDP services automatically log API failures to the WordPress error log and the internal `Log` service.
- Use `try/catch` blocks when performing write operations.
- Check for `false` or `null` return values on fetch operations.

## Configuration
Credentials (JWT, API Endpoint, etc.) are managed via the **ACC Options** page in the WordPress admin. These are stored as WordPress options and retrieved by `WACC()->Mdp()->Init`."
