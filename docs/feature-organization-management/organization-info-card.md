# Organization Info Card Block

## Overview
The Organization Info Card block provides a compact summary of an organization's essential data, typically used in dashboards or parent organization overviews.

## Block Details
- **Slug**: `wicket-ac/organization-info-card`
- **Template**: `templates-wicket/blocks/account-centre/organization-management.php` (when in card mode)

## Core Functionality

### 1. Summary Information
The block displays a high-level view of:
- **Organization Logo**: Fetched via the `OrganizationProfile` service.
- **Legal Name**: Primary organization name from the MDP.
- **Membership Status**: Current active status (e.g., Active, Expired).
- **Primary Address**: Formatted address for quick identification.

### 2. Quick Actions
The card can be configured to show links for:
- **View Profile**: Direct link to the full organization profile.
- **Manage Members**: Quick link to the roster.
- **Switch Context**: Action to set the current organization context in the selector.

## Technical Implementation

### Core Services
- `WACC()->Mdp()->Organization()->getOrganizationByUuid()`: Fetches the core organization record.
- `WACC()->OrganizationProfile()->get_organization_logo()`: Retrieves the local or default logo URL.

### Styling
- The card uses the `wicket-acc-org-card` CSS class.
- Styling is primarily managed through **TailwindCSS** for a responsive, modern look.

## ACF Configuration

| Field | Description |
|       |             |
| `display_mode` | Choose between a horizontal (compact) or vertical (full) card layout. |
| `show_address` | Toggles the visibility of the primary address. |
| `show_actions` | Toggles the visibility of quick action links. |
| `custom_labels` | Allows for overriding the default "View Profile" and "Manage Members" labels. |

## Access Control
- **Viewing**: Authenticated users who have a verified relationship with the organization.
- **Action Links**: Some links (like Manage Members) are dynamically hidden if the user lacks the required `membership_manager` role.

## Security Best Practices
- **UUID Sanitization**: Always sanitize the organization UUID used for the card's context.
- **Role Validation**: Action links must be conditionally rendered based on real-time role checks from the MDP.
