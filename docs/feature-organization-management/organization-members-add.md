# Organization Members Add Block

## Overview
The Organization Members Add block provides an interface for organization managers to invite new people to their organization or add them directly to a membership.

## Block Details
- **Slug**: `wicket-ac/organization-members-add`
- **Template**: `templates-wicket/blocks/account-centre/organization-management.php` (when in add-member mode)

## Core Functionality

### 1. Person Search & Creation
- **Search**: Users can search for existing people in the MDP by email or UUID.
- **Creation**: If the person is not found, the block allows creating a new person record with basic details (First Name, Last Name, Email).
- **Service**: `WACC()->User()->createOrUpdateWpUser()` (handles both MDP and WP side creation).

### 2. Relationship Assignment
Once a person is identified or created, the manager can:
- **Assign Roles**: Select from available MDP roles (e.g., `member`, `org_editor`).
- **Associate Membership**: Link the person to a specific organization membership instance.
- **Service**: `WACC()->Mdp()->Membership()->assignPersonToOrgMembership()`.

### 3. Role Synchronization
The block automatically triggers role synchronization between the MDP and WordPress:
- **Service**: `WACC()->Mdp()->Roles()->updateRoles()` (handles the combined update).

## Technical Implementation

### Core Services
- `Mdp\Person`: API interaction for person lookups and creation.
- `Mdp\Membership`: API interaction for establishing organization relationships.
- `Mdp\Roles`: API interaction for initial role assignment.

### Dynamic Interaction
The block is built with **Datastar** to provide real-time feedback during the search and creation process:
- **Email Validation**: Real-time checking if a user with the provided email already exists.
- **Form States**: Dynamic transition between "Search Person" and "Assign Roles" steps.

## ACF Configuration

| Field | Description |
|       |             |
| `default_roles` | A comma-separated list of roles that are pre-selected for new members. |
| `require_email_verification` | Toggles whether a newly created person must verify their email before becoming active. |
| `max_members_limit` | Optional limit on the number of members that can be added (if not managed by the membership plan). |

## Access Control
- **Usage**: Restricted to users with `administrator`, `membership_manager`, or `membership_owner` roles for the current organization.
- **Target Role Assignment**: Managers can only assign roles that they themselves possess (unless configured otherwise).

## Security Best Practices
- **Nonce Verification**: All member addition actions must be protected by nonces.
- **Email Sanitization**: Use `sanitize_email()` for all person lookups and creation steps.
- **Capability Checks**: Explicitly verify manager permissions before performing any MDP write operations.
