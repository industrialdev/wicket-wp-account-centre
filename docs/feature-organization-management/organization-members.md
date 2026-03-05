# Organization Members (Roster) Block

## Overview
The Organization Members block (also known as the Roster) displays a paginated list of members affiliated with an organization. It allows managers to search for members, edit their roles, and manage their relationship status.

## Block Details
- **Slug**: `wicket-ac/organization-members`
- **Component**: Uses the shared `roster-management` UI component.

## Key Features

### 1. Paginated Member List
The block fetches members associated with an organization's membership record.
- **Service**: `WACC()->Mdp()->Membership()->getOrganizationMembershipMembers()`
- **Default Page Size**: 20 members per page.

### 2. Member Filtering & Search
- Users can filter the roster by name, email, or membership status.
- Real-time search is supported via **Datastar** for a seamless experience.

### 3. Role Management
Managers (with appropriate permissions) can:
- Assign roles such as `org_editor` or `membership_manager`.
- Revoke roles from existing members.
- **Service**: `WACC()->Mdp()->Roles()->updateRoles()`

### 4. Relationship Management
- **Addition**: Invite new members by email and assign initial roles.
- **Removal**: End-date a relationship or perform a complete removal from the organization.
- **Service**: `WACC()->Mdp()->Membership()->unassignPersonFromOrgMembership()`

## Technical Implementation

### Core Services
The block relies on several services via `WACC()`:
- `OrganizationRoster`: Logic for identifying membership UUIDs.
- `Mdp\Membership`: API interaction for member lists.
- `Mdp\Roles`: API interaction for role changes.
- `User`: Synchronizes role changes with WordPress.

### Initialization Flow
1. **Identify Context**: Determine organization UUID from URL parameter (`org_uuid`).
2. **Access Check**: Verify the current user has `administrator` or `membership_manager` roles for that organization.
3. **Fetch Membership**: Retrieve the membership instance for the organization.
4. **Render Roster**: Output the member list using the configured card display fields.

## ACF Configuration

| Field | Description |
|       |             |
| `per_page` | Number of members to show per page. |
| `display_search` | Toggle search bar visibility. |
| `display_fields` | Checkbox list of fields to show on cards (Salutation, Suffix, Member ID, etc.). |
| `removal_mode` | Policy for removing members (Complete Removal or Set End Date). |

## Access Control
Permissions are verified using `WACC()->Mdp()->Organization()->getOrganizationPersonRoles()`:
- **View Roster**: `member`, `org_editor`.
- **Manage Roster**: `administrator`, `membership_manager`, `membership_owner`.
