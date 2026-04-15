---
title: "Organization Profile View"
audience: [implementer, support]
php_class: Wicket_ACC_Main
---

# Organization Profile View Block

## Overview
The Organization Profile View block displays detailed metadata for a specific organization, including its legal name, status, and associated information schemas.

## Block Details
- **Slug**: `wicket-ac/ac-org-profile`
- **Class**: `WicketAcc\Blocks\OrgProfile\init`

## Core Functionality

### 1. Dynamic Context Identification
The block automatically determines which organization to display:
- **URL Parameter**: Prioritizes `child_org_id` then `org_uuid`.
- **User Association**: If no ID is provided, it fetches all organizations where the user has an `org_editor` role. If only one exists, it displays that organization.

### 2. Profile Display & Editing
The block integrates with **Wicket Widgets** (`widgets.js`) to provide an interactive profile editing interface:
- **Widget**: `editOrganizationProfile`
- **Fields**: Supports custom field selection via the `mdp_json_fields` setting.

### 3. Additional Information
If enabled, the block also displays the **Additional Info** widget for the organization:
- **Widget**: `editAdditionalInfo`
- **Scope**: Organization-specific schemas (e.g., certifications, interests).

## Technical Implementation

### Initialization
The `init` class in `includes/blocks/ac-org-profile/init.php` handles:
- Language resolution.
- API client authentication.
- Retrieval of organization data via `WACC()->Mdp()->Organization()->getOrganizationByUuid()`.

### Frontend Rendering
The block outputs a simple container and initializes the Wicket Widgets via inline JavaScript.

```javascript
Wicket.widgets.editOrganizationProfile({
    rootEl: widgetRoot,
    apiRoot: apiEndpoint,
    accessToken: accessToken,
    orgId: orgUuid,
    lang: currentLang,
    fields: jsonFields,
    hiddenFields: ['alternateName']
});
```

## ACF Configuration

| Field | Description |
|       |             |
| `hide_additional_info` | Toggles the display of the Additional Info widget. |
| `hide_alternate_name_field` | Explicitly hides the Alternate Name field in the profile editor. |
| `mdp_json_fields` | A JSON string defining which MDP fields should be editable. |

## Access Control
- **Viewing**: Authenticated users associated with the organization.
- **Editing**: Users must have the `org_editor` role for the specific organization in the MDP."
