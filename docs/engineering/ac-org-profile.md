---
title: "AC Org Profile Block"
audience: [developer, agent, implementer]
php_class: WicketAcc\Blocks\OrgProfile\init
source_files: ["includes/blocks/ac-org-profile/init.php", "includes/blocks/ac-org-profile/render.php", "includes/blocks/ac-org-profile/block.json", "includes/acf-json/group_69a5f1b6ea37e.json"]
---

# AC Org Profile Block

## Overview

The Org Profile block renders and edits a single organization's profile inside the account area. It mounts the `Wicket.widgets.editOrganizationProfile` widget and passes the MDP field/section config into the widget so editors can configure what is shown.

## Block Architecture

### Directory Structure

```
includes/blocks/ac-org-profile/
├── block.json         # Block registration
├── init.php           # WicketAcc\Blocks\OrgProfile\init
└── render.php         # Template renderer
```

Extends `WicketAcc\Blocks` (see [base-block.md](base-block.md)).

## Core Functionality

### URL-Based Org Resolution

The block resolves which organization to display from the request:

- `?org_uuid=` or `?org_id=` selects the parent org.
- `?child_org_id=` overrides `org_id` when present (used for parent/child org flows).

If no org is in the URL, the block falls back to the user's `org_editor` role associations and:

- renders the single matching org directly when the user only has one, or
- surfaces a chooser when the user has more than one (handled by `ac-org-search-select`).

### Configuration Surface

The block reads these ACF fields:

- `hide_additional_info` — hides the additional-info widget when truthy.
- `hide_alternate_name_field` — adds `alternateName` to the widget's `hiddenFields`.
- `mdp_json_fields` — JSON string decoded into the `fields` array passed to the widget.
- `mdp_json_sections` — JSON string decoded into the `sections` array passed to the widget.

`mdp_json_sections` lets editors declare the section order independently from the field list.

### Language And API

- Uses `WACC()->Language()->getCurrentLanguage()` to resolve the active language (WPML / Polylang / site default).
- Pulls the org through `WACC()->Mdp()->Organization()->getOrganizationByUuid()` and mints an org-scoped access token via `wicket_get_access_token($person_uuid, $org_id)`.

### Widget Integration

The block mounts the org-profile widget:

```js
Wicket.widgets.editOrganizationProfile({
    rootEl: widgetRoot,
    apiRoot: wicket_settings['api_endpoint'],
    accessToken: access_token,
    orgId: org_id,
    lang: lang,
    fields: mdp_json_fields,
    sections: mdp_json_sections, // when present
    hiddenFields: ['alternateName', ...]
});
```

## Recent Changes

- Added `mdp_json_sections` config so editors can declare the section order independently from the field list.

## Related Documentation

- [Base Block](base-block.md)
- [Organization Search Select Block](ac-org-search-select.md) — picker used when the user has more than one org.
- [Org Logo Block](ac-org-logo.md) — companion block for the logo.