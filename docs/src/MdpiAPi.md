# ACC MDP API Class Documentation

## Overview
The `MdpApi` class serves as a dedicated wrapper for the Wicket PHP SDK (`Wicket\Client`), providing a simplified interface for interacting with the Member Data Platform (MDP) API. It handles client initialization, authentication, and provides methods to fetch and manage data such as people, organizations, and touchpoints.

## Class Definition
```php
namespace WicketAcc;

use Exception;
use Wicket\Client;

class MdpApi
{
    /**
     * Constructor.
     */
    public function __construct();
}
```

## Core Methods

### Client and Configuration
- `init_client(): Client|false`: Initializes and returns a configured `Wicket\Client` instance for making API calls, or `false` on failure.
- `get_option(string $key, $default = null): mixed`: Retrieves a specific setting from the `wicket_settings` WordPress option.
- `mdp_get_settings($environment = null): array`: Gets the appropriate Wicket MDP API credentials (API endpoint, JWT, person ID, parent org, Wicket admin) based on the selected environment (Production or Staging), retrieved from `wicket_settings`.

### Person Data
- `get_current_person_uuid(): string|false`: Retrieves the UUID (user login) of the currently logged-in WordPress user, or `false` if the Wicket SDK client function `wicket_api_client` isn't available.
- `get_current_person(): object|false`: **CURRENTLY NON-FUNCTIONAL DUE TO A LOGIC BUG.** Intends to fetch the complete MDP data object for the current person using their UUID. However, due to a logic error (`if (!empty($person_id)) { return false; }`), it will incorrectly return `false` if a person UUID is found. If no UUID is found, it will attempt to fetch with an invalid ID, also leading to failure. As it stands, this method will effectively always return `false` or fail.

### Organization Data
- `get_organization_info(string $org_uuid, string $lang = 'en'): array|false`: Retrieves and aggregates detailed information for a specific organization. This includes the core organization data, its parent organization's name and UUID (if any), organization type details (including a 'nice name'), and associated addresses, phone numbers, and email addresses. Returns `false` if the organization UUID is empty or an error occurs.
- `get_organization_by_uuid(string $uuid = ''): array|false`: Fetches a single organization's raw data array by its UUID. Returns `false` on failure or if UUID is empty.
- `get_organization_membership_by_uuid(string $uuid = ''): array|false`: Fetches a single organization membership record as an array by its UUID. Returns `false` on failure or if UUID is empty.
- `get_organization_memberships(string $org_uuid): array|false`: Retrieves all membership entries (and their included membership data) for a given organization UUID, sorted by `ends_at` descending. Returns `false` if `org_uuid` is empty or no memberships are found.

### Touchpoints
- `get_current_user_touchpoints(string $service_id, string $person_id = null): array`: Gets all touchpoints for a given service ID and person ID. If `person_id` is null, it uses the current user's UUID. Returns an empty array if no touchpoints are found or an error occurs.
- `create_touchpoint_service_id(string $service_name, string $service_description = 'Custom from WP'): string|false`: Retrieves an existing touchpoint service ID by its name. If the service doesn't exist, it creates a new one with the given name and description, then returns its ID. Returns `false` on failure.

## Usage Example
The `MdpApi` class is accessible via the `WACC()` global function.

```php
// Get the current person's data from the MDP
$person_data = WACC()->mdp_api()->get_current_person();

if ($person_data) {
    // Do something with the data
}

// Get information for a specific organization
$org_info = WACC()->mdp_api()->get_organization_info('org-uuid-123');
```

## Error Handling
- The methods are designed to be fault-tolerant. They catch exceptions from the underlying Wicket SDK.
- On failure (e.g., API error, invalid UUID), methods typically return `false` or an empty array. It is important to check the return value before using it.
