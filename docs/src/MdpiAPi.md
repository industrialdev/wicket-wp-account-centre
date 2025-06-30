# ACC MDP API Class Documentation

## Overview
The `Mdp` class serves as a dedicated wrapper for the Wicket PHP SDK (`Wicket\Client`), providing a simplified interface for interacting with the Member Data Platform (MDP) API. It handles client initialization, authentication, and provides methods to fetch and manage data such as people, organizations, and touchpoints.

## Class Definition
```php
namespace WicketAcc;

use Exception;
use Wicket\Client;

class Mdp
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
- `getOption(string $key, $default = null): mixed`: Retrieves a specific setting from the `wicket_settings` WordPress option.
- `getMdpSettings($environment = null): array`: Gets the appropriate Wicket MDP API credentials (API endpoint, JWT, person ID, parent org, Wicket admin) based on the selected environment (Production or Staging), retrieved from `wicket_settings`.

### Person Data
- `getCurrentPersonUuid(): string|false`: Retrieves the UUID (user login) of the currently logged-in WordPress user, or `false` if the Wicket SDK client function `wicket_api_client` isn't available.
- `getCurrentPerson(): array|false`: Fetches the complete Wicket Person data array for the current logged-in user. Returns `false` if not logged in or an error occurs during data retrieval.
- `getPersonByUuid(string $uuid): array|false`: Fetches a single person's raw data array by their UUID. Returns `false` on failure or if UUID is empty.
- `getPersonProfileByUuid(string $person_uuid): array|null`: Retrieves a person's profile information by their UUID, including related data like addresses, emails, and phones. Returns `null` on failure or if the person is not found.
- `getPersonRepeatableContactInfo(object $person_data, string $contact_type, bool $return_full_objects = false): array|false`: Extracts and returns a filtered array of a specific contact method type (e.g., "addresses", "emails") from a Wicket person data object. Can return full contact objects or just their attributes. Returns `false` on failure or if no matching contacts are found.

### Membership Data
- `getOrganizationMembershipByUuid(string $uuid): array|false`: Retrieves a specific organization membership by its UUID. Returns `false` if the UUID is empty, the API client fails to initialize, or an API exception occurs.
- `getOrganizationMemberships(string $org_uuid): array|false`: Retrieves all membership entries (and their included membership data) for a given organization UUID, sorted by `ends_at` descending. Returns an empty array `[]` if the organization has no memberships but the API call is successful. Returns `false` if `org_uuid` is empty, the API client fails to initialize, the API response is malformed, or an API exception occurs.

### Organization Data
- `getOrganizationByUuid(string $uuid): array|false`: Retrieves organization data by its UUID. Returns `false` if the UUID is empty, the API client fails to initialize, or an API exception occurs.
- `getOrganizationInfo(string $org_uuid, string $lang = 'en'): array|false`: Retrieves detailed organization information, including parent organization name and contact details (address, phone, email). Returns `false` if `org_uuid` is empty, the base organization data cannot be retrieved, or an API exception occurs during contact detail fetching.
### Touchpoints
- `getCurrentUserTouchpoints(string $service_id, ?string $person_id = null): array|false`: Gets all touchpoints for a given service ID and person ID. If `person_id` is null, it uses the current user's UUID. Returns an array of touchpoints or `false` on failure.
- `getOrCreateServiceId(string $service_name, string $service_description = 'Custom from WP'): string|null`: Retrieves an existing touchpoint service ID by its name. If the service doesn't exist, it creates a new one with the given name and description, then returns its ID. Returns `null` on failure.
- `writeTouchpoint(array $params, string $wicket_service_id): bool`: Writes a touchpoint to the MDP. Requires parameters like `person_id`, `action`, `details`, etc., and a `wicket_service_id`. Returns `true` on success, `false` on failure. See PHPDoc in `Touchpoint.php` for detailed `params` structure.

## Usage Example
The `Mdp` class is accessible via the `WACC()` global function.

```php
// Get the current person's data from the MDP
$person_data = WACC()->mdp_api()->getCurrentPerson();

if ($person_data) {
    // Do something with the data
}

// Get information for a specific organization
$org_info = WACC()->Mdp->Organization->getOrganizationInfo('org-uuid-123');
```

## Error Handling
- The methods are designed to be fault-tolerant. They catch exceptions from the underlying Wicket SDK.
- On failure (e.g., API error, invalid UUID), methods typically return `false` or an empty array. It is important to check the return value before using it.
