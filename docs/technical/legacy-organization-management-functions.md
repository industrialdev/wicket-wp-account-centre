# Old Organization Management Functions Documentation

This document provides a comprehensive list of functions used for organization management in the Wicket WordPress plugin.

## Core Organization Functions

### Organization Information & Retrieval

```php
wicket_orgman_get_organization_info_extended($org_uuid, $lang = 'en')
```
- Gets extended organization information including address, phone, email
- Returns array with org metadata or false

```php
wicket_orgman_get_organization_engagement($org_id)
```
- Fetches organization's engagement data from MDP
- Returns organization engagement data or false

```php
wicket_orgman_get_organization_documents($org_id)
```
- Gets organization documents
- Returns array of documents or false

### Member Management

```php
wicket_orgman_get_organization_members($org_id, $args)
```
- Gets organization members with optional search/pagination
- Args include: search, page, limit
- Returns array of members or false

```php
wicket_orgman_membership_search_members($membership_uuid, $args)
```
- Searches members within a specific membership
- Includes pagination and search query options
- Returns search results array or false

```php
wicket_orgman_update_member_permissions($person_uuid, $args)
```
- Updates member roles and relationship to organization
- Takes person UUID and array of permissions args
- Returns boolean success status

### Role Management

```php
wicket_orgman_get_available_roles()
```
- Returns array of available organization management roles

```php
wicket_orgman_get_person_current_roles_by_org_id($person_uuid, $org_id)
```
- Gets person's current roles in an organization
- Returns array of roles or false

```php
wicket_orgman_role_check($roles, $org_id, $all_true)
```
- Checks if user has specified role(s)
- `all_true`: requires all roles vs any role
- Returns boolean

```php
wicket_orgman_person_has_org_roles($person_uuid, $roles, $org_id, $all_true)
```
- Checks if person has specific roles in organization
- Similar to role_check but for specific person
- Returns boolean

### Contact Information Management

```php
wicket_orgman_clear_org_phones($org_id)
wicket_orgman_clear_org_emails($org_id)
wicket_orgman_clear_org_websites($org_id)
wicket_orgman_clear_org_addresses($org_id)
```
- Functions to clear respective contact information
- Return boolean success status

```php
wicket_orgman_create_or_update_organization_phone($org_id, $payload)
wicket_orgman_create_or_update_organization_email($org_id, $payload)
wicket_orgman_create_or_update_organization_website($org_id, $payload)
```
- Functions to create/update contact information
- Take organization ID and payload data
- Return boolean success status

### Business Information

```php
wicket_orgman_business_info_send_section_patch($org_id, $payload)
```
- Updates organization's business information section
- Returns array|bool

```php
wicket_orgman_get_org_additional_info($org_uuid, $segment, $lang)
```
- Gets organization's additional information for a segment
- Returns array or false

### Relationship Management

```php
wicket_orgman_end_relationship_today($person_uuid, $relationship_id, $org_id)
```
- Sets end date for relationship to today
- Returns boolean

```php
wicket_orgman_get_person_tags_for_org($person_uuid, $org_id)
```
- Gets tags for person-organization connection
- Returns array|false

### UI Components

```php
wicket_orgman_render_organization_info_card($args)
```
- Renders organization card with logo, summary and child orgs
- Takes array of org details as argument

```php
wicket_orgman_render_organization_tabs($args)
```
- Renders navigation tabs for org management
- Takes array with active tab, URLs, org data

### Utility Functions

```php
wicket_orgman_redirect_or_die($response, $args)
```
- Handles redirects and error responses
- Takes response data and arguments

```php
wicket_orgman_continue_or_die($response)
```
- Validates response and continues or dies
- Takes response data

```php
wicket_orgman_htmx_url()
```
- Returns URL for HTMX endpoint

```php
wicket_orgman_readCSV($filename)
```
- Reads and caches CSV file data
- Returns array of data

### File Storage & Cache

```php
wicket_orgman_getCountries()
wicket_orgman_getStatesProvinces()
```
- Read and return cached location data
- Return arrays of location information

## Important Notes

- Most functions require authentication with MDP (Wicket API)
- Many functions use global variables defined in initialization
- Error handling typically returns false or triggers wp_die()
- Some functions depend on WordPress roles and capabilities
- HTMX is used for dynamic content loading
- Caching is implemented for expensive operations

## Usage Examples

```php
// Get organization info
$org_info = wicket_orgman_get_organization_info_extended($org_uuid, 'en');

// Check user roles
$has_role = wicket_orgman_role_check(['admin', 'editor'], $org_id, false);

// Update member permissions
$updated = wicket_orgman_update_member_permissions($person_uuid, [
    'org_id' => $org_id,
    'roles' => ['editor'],
    'relationship_type' => 'member'
]);
```
