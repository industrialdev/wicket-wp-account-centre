# Organization Profile Edit Block

## Overview
The Organization Profile Edit block allows authorized users to modify organization metadata, such as its name, type, and associated information. It typically operates as a companion to the view block or as a standalone management interface.

## Block Details
- **Slug**: `wicket-ac/organization-profile-edit`
- **Class**: `WicketAcc\Blocks\OrgProfile\init` (handles both view and edit modes).

## Core Functionality

### 1. Metadata Management
The block provides a form-based interface for editing:
- **Legal Name**: The organization's official name.
- **Alternate Name**: Optional nicknames or secondary names.
- **Organization Type**: Select from types defined in the MDP.
- **Organization Status**: Toggle between Active/Inactive/Duplicate (for admins).

### 2. Information Sections
The block can be configured to show specific information categories:
- **Primary Address**: Physical and mailing addresses.
- **Contact Info**: Phone numbers and email addresses.
- **Web Presence**: Website and social media URLs.

### 3. Service Integration
- **Service**: `WACC()->Mdp()->Organization()->update()` (handles the actual API update).
- **Service**: `WACC()->OrganizationProfile()` (handles logo management during profile updates).

## Technical Implementation

### Dynamic UI (Datastar)
The edit block uses **Datastar** to perform:
- **Real-time Validation**: Check required fields or unique values as the user types.
- **Partial Saving**: Save specific sections (e.g., Address only) without reloading.
- **Dynamic Options**: Fetch organization types or countries from the MDP as the user interacts with the form.

### Form Processing
The block's `init` class handles form submission:
1. **Nonce Check**: Verify the `wicket-acc-org-profile-edit` nonce.
2. **Sanitization**: All input data is passed through `sanitize_text_field()` or specialized functions.
3. **API Call**: Data is sent to the MDP via `WACC()->Mdp()->Organization()`.
4. **Result Handling**: Display success messages or clear error feedback from the API.

## ACF Configuration

| Field | Description |
|       |             |
| `editable_fields` | A checkbox list of which fields should be allowed to be edited. |
| `require_verification` | Toggles whether updates must be reviewed by a system administrator. |
| `custom_success_message` | Allows overriding the default message shown after a successful save. |

## Access Control
Permissions are strictly verified:
- **Usage**: Restricted to users with the `org_editor`, `membership_owner`, or `administrator` role for the current organization.
- **Field-level Security**: Some fields (like Legal Name) may be restricted to system administrators only.

## Security Best Practices
- **Sanitize Input**: Always sanitize organization UUIDs and all field values before processing.
- **Nonce Protection**: Form submissions must be protected with nonces.
- **Auditing**: Changes are typically logged to the internal `Log` service for auditing purposes.
