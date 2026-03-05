# Organization Business Information Block

## Overview
The Organization Business Information block provides an interface for editing specific organization details that are not covered in the standard profile view, such as tax IDs, operational status, or certifications.

## Block Details
- **Slug**: `wicket-ac/organization-business-info`
- **Template**: `templates-wicket/blocks/account-centre/organization-management.php` (when in business-info mode)

## Core Functionality

### 1. Section-based Editing
The block divides business information into configurable sections:
- **Operational Status**: Details on the organization's current status and employee count.
- **Retail Presence**: Information on physical locations and service types.
- **Revenue**: Financial data like revenue range and currency.
- **Certifications**: List of official organization certifications.

### 2. Wicket Widget Integration
Like the main Profile block, this block often uses **Wicket Widgets** for the actual editing interface:
- **Widget**: `editAdditionalInfo` (specifically for organization resources).
- **Endpoint**: Fetches and saves data to the `/organizations/{uuid}/additional-info` MDP endpoint.

### 3. Visibility Controls
The block can be hidden based on a date range configured in the block settings, allowing for time-limited information updates (e.g., annual data collection).

## Technical Implementation

### Service Access
- **Service**: `WACC()->Mdp()->Organization()->getOrganizationByUuid()`
- **Logic**: The block identifies the current organization from the URL context (`org_uuid` or `child_org_id`).

### Dynamic UI
- Uses **Datastar** for section-level loading and real-time validation feedback.
- If using widgets, the `editAdditionalInfo` JavaScript component handles the entire lifecycle of data fetching and saving.

## ACF Configuration

| Field | Description |
|       |             |
| `available_sections` | A checkbox list of which business info sections to enable. |
| `date_control` | Toggles the date-based visibility for the block. |
| `start_date` / `end_date` | The date range during which the block is visible. |
| `info_message` | A message to display to the user when the block is outside the active date range. |

## Access Control
- **Viewing**: Authenticated users associated with the organization.
- **Editing**: Only users with `administrator`, `membership_owner`, or `org_editor` roles in the MDP for that organization.
