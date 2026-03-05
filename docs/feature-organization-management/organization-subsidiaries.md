# Organization Subsidiaries Block

## Overview
The Organization Subsidiaries block allows a parent organization's manager to view and manage child organizations (subsidiaries or locations) associated with their account.

## Block Details
- **Slug**: `wicket-ac/organization-subsidiaries`
- **Template**: `templates-wicket/blocks/account-centre/organization-management.php` (when in subsidiary mode)

## Core Functionality

### 1. Subsidiary Listing
The block lists all organizations where the current organization is the parent.
- **Service**: `WACC()->Mdp()->Organization()->getOrganizationChildren($parent_org_uuid)`
- **Data Display**: Shows organization name, primary address, and action links.

### 2. Subsidiary Management Actions
Managers can perform the following actions for each subsidiary:
- **Edit Profile**: Links to the organization profile page for the child organization.
- **Manage Members**: Links to the roster management page for the child organization.
- **Add New Subsidiary**: A global action to create a new child organization under the current parent.

## Technical Implementation

### Template Routing
The `OrganizationManagement` service filters the template resolution via the `wicket/acc/orgman/is_orgmanagement_page` filter. If the page slug starts with `organization-subsidiaries`, it loads the corresponding management template.

### Navigation Context
When managing a subsidiary, the URL includes the `child_org_id` parameter. This informs all related blocks (Profile, Roster, Documents) that they should operate on the child organization instead of the parent.

## Access Control
Permissions are hierarchical:
- **View Subsidiaries**: Anyone with an `administrator` or `membership_manager` role in the **parent** organization.
- **Manage Subsidiary**: Users with the `org_editor` role for the **parent** can typically manage all child organizations as well.
- **Create Subsidiary**: Restricted to `administrator` or `membership_owner` of the parent organization.

## Security Best Practices
- **UUID Validation**: All subsidiary operations must validate that the `child_org_id` actually has the `parent_org_id` as its parent in the MDP before allowing modifications.
- **Nonce Protection**: Form submissions for creating or updating subsidiaries must be protected with nonces.
