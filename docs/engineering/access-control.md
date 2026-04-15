---
title: "Access Control"
audience: [developer, agent]
php_class: Wicket_ACC_Main
source_files: ["src/"]
---

# Access Control & Roles

## Overview
Access control in the Wicket Account Centre is determined by a combination of WordPress user roles and Wicket Member Data Platform (MDP) roles.

## MDP Roles
Roles in the MDP are assigned to a person's relationship with an organization or membership. Common roles include:
- `administrator`: Full access to the organization and its data.
- `org_editor`: Can edit organization profile details.
- `membership_manager`: Can manage member lists and roles.
- `member`: Standard access to organization resources.

## Technical Implementation

### 1. Fetching MDP Roles
To retrieve roles for a specific person within an organization, use the `Organization` service:

```php
// Returns an array of role names or false on failure
$roles = WACC()->Mdp()->Organization()->getOrganizationPersonRoles($personUuid, $orgUuid);

if ($roles && in_array('org_editor', $roles)) {
    // User can edit the organization
}
```

### 2. Managing Roles
The `Roles` service handles assignment and removal of roles within the MDP:

```php
// Assign a role
WACC()->Mdp()->Roles()->assignRole($personUuid, 'Board Member', $orgUuid);

// Remove a role
WACC()->Mdp()->Roles()->removeRole($personUuid, 'Board Member');

// Batch update roles (syncs with WP roles automatically)
WACC()->Mdp()->Roles()->updateRoles([
    'person_uuid' => $personUuid,
    'org_uuid'    => $orgUuid,
    'roles'       => ['member', 'org_editor'],
    'person_current_roles' => ['member']
]);
```

### 3. WordPress Role Synchronization
The plugin automatically attempts to synchronize specific MDP roles with WordPress user roles to maintain consistent permissions:
- **Class**: `WicketAcc\User`
- **Methods**: `assignWpRoles()`, `removeWpRoles()`

Example: If a user is granted `org_editor` in the MDP, the corresponding WordPress user will have the `org_editor` role added to their WP profile.

## Access Control in Blocks
Most organization management blocks perform role checks during initialization:
1. **Current Context**: Identify the target organization (often via URL parameter).
2. **Role Check**: Fetch roles for the current user in that organization.
3. **Capability Validation**: If the user lacks the required role, the block displays a "Permission Denied" message or redirects.

## Security Best Practices
- **Sanitization**: Always sanitize organization UUIDs before performing role checks.
- **Nonce Validation**: All role management actions (e.g., via AJAX or form submission) must include a valid WordPress nonce.
- **Capability Mapping**: Use `current_user_can()` for WordPress-level permissions and the MDP services for organization-specific permissions."
