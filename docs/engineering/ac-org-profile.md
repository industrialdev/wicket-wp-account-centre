---
title: "AC Org Profile Block"
audience: [developer, agent, implementer]
php_class: WicketAcc\Blocks\OrgProfile\init
source_files: ["includes/blocks/ac-org-profile/init.php", "includes/blocks/ac-org-profile/render.php", "includes/blocks/ac-org-profile/block.json", "includes/acf-json/group_66bd0f5a9fca8.json", "../wicket-wp-base-plugin/includes/components/widget-profile-org.php"]
---

# AC Org Profile Block

## Overview

The Org Profile block renders and edits a single organization's profile inside the account area. It renders through the base plugin's shared `widget-profile-org` component (as of the widget-config refactor — previously it mounted `Wicket.widgets.editOrganizationProfile` inline, predating the component's creation) and passes the MDP field config through so editors can configure what is shown. Unlike the individual profile component, `widget-profile-org` has no `sections` arg — there is no org equivalent of the individual block's `mdp_json_sections`.

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

- `hide_additional_info` — hides the additional-info widget when truthy. Block-side toggle, untouched by the widget-config refactor.
- `hide_alternate_name_field` — adds `alternateName` to the widget's `hiddenFields`. Since the shared component has no dedicated `hidden_fields` arg for the org widget, this is passed via `widget_config['hiddenFields']`.
- `mdp_json_fields` — JSON string decoded into the `fields` array passed to the component. Legacy; ignored entirely when `mdp_json_config` is set.
- `mdp_json_config` — open-ended JSON object forwarded verbatim to the component as `widget_config` (any current or future MDP widget option). See "MDP Widget Config" below.
- `mdp_json_sections` — **inert.** This field is never read by `init.php` and has no effect: the
  org profile widget component has no `sections` arg (unlike the individual profile component), so
  any value ever saved here has always been silently dropped at render time — before and after the
  widget-config refactor. It's kept in the ACF group, visible and unhidden, purely so a value saved
  before this was noticed isn't hidden from whoever configured the block. It gets no migrate link
  and no CodeMirror (see `assets/js/wicket-acc-acf-field-deprecation.js`,
  `isInertOrgSectionsField()`). Use `mdp_json_config`'s `sections` key instead if the MDP widget
  ever adds real section support for organization profiles.

### Language And API

- Uses `WACC()->Language()->getCurrentLanguage()` to resolve the active language (WPML / Polylang / site default) — genuinely more robust than the component's own fallback (see below).
- Pulls the org through `WACC()->Mdp()->Organization()->getOrganizationByUuid()` and mints an org-scoped access token via `wicket_get_access_token($person_uuid, $org_id)` — unchanged by the refactor; the shared component derives the token the same way internally.

### Widget Integration (refactored onto the shared component)

The block now renders through `get_component('widget-profile-org', ...)` (base plugin,
`includes/components/widget-profile-org.php`) instead of an inline
`Wicket.widgets.editOrganizationProfile` call:

```php
if (is_array($widget_config) && $widget_config !== []) {
    if (!empty($hidden_fields)) {
        $widget_config['hiddenFields'] = $hidden_fields;
    }
    $widget_config['lang'] = $lang;
    get_component('widget-profile-org', [
        'org_id'        => $org_id,
        'widget_config' => $widget_config,
    ]);
} else {
    get_component('widget-profile-org', [
        'org_id'   => $org_id,
        'fields'   => $mdp_json_fields,
        'widget_config' => ['lang' => $lang, 'hiddenFields' => $hidden_fields], // hiddenFields only when non-empty
    ]);
}
```

**`lang` is routed through `widget_config`, not left to the component's default (post-refactor
fix, caught in review after ship).** The component has no dedicated `lang` arg — left alone, it
falls back to `defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en'`, a much weaker check than
`WACC()->Language()->getCurrentLanguage()` (no Polylang support, no WP-locale fallback, and
`ICL_LANGUAGE_CODE` is an older WPML constant). The block already computes `$lang` correctly for
the Additional Info widget mount below; the initial refactor forgot to also route it into the
org-profile widget's own call, so non-English/non-WPML-primary-language editors briefly got the
wrong widget language after the refactor landed. Fixed by adding `$widget_config['lang'] = $lang`
in both branches — last-wins emit order in the component means this always overrides the
component's own fallback.

The additional-info widget mount (`Wicket.widgets.editAdditionalInfo`), the `hide_additional_info`
toggle, and all org-selection/chooser logic stay block-side and are untouched — only the
`editOrganizationProfile` mount moved to the component. The component emits its own `window.Wicket`
bootstrap loader (guarded against double-definition), its own wrapper `<div>`, an
`<h2>Organization Profile</h2>` heading, and hidden inputs (`org_info_data_field_name` etc.) that
the inline version did not have — the block's own `<h2>Profile</h2>` heading was dropped to avoid a
duplicate heading. Minor markup/DOM differences vs. the pre-refactor block are expected and are not
pixel-parity bugs.

`component_exists('widget-profile-org')` is checked before rendering, matching the GF org field's
defensive pattern — shows a "please update the Wicket Base Plugin" message if the component is
missing rather than fataling.

### MDP Widget Config (open-ended passthrough)

Same pattern as the [Individual Profile block](ac-individual-profile.md#mdp-widget-config-open-ended-passthrough):
exclusive precedence over the legacy `mdp_json_fields` (config replaces it, not merged).
`mdp_json_config` is the top-most field in the ACF group; the legacy field sits below it,
labeled "(Deprecated)" in plain text, stays plain `<textarea>` (no CodeMirror), and carries a
"Copy this value into MDP Widget Config" link
(`assets/js/wicket-acc-acf-field-deprecation.js`, shared across both AC profile blocks since it
matches by ACF field name) that merges its value into the config field under the `fields` key —
click-triggered only, never automatic. Same accepted risk as the individual block: a
schema-agnostic config can express unsatisfiable option combinations the widget cannot resolve.
See [refactor-gf-mdp-widget-config.md](../../../../../wicket-atlas/plans/refactor-gf-mdp-widget-config.md).

### Editor UI (CodeMirror, config field only)

Same CodeMirror JSON editor as the [Individual Profile block](ac-individual-profile.md#editor-ui-codemirror-config-field-only) —
`assets/js/wicket-acc-acf-json-editor.js` is shared across both AC profile blocks (matches by
ACF field name), and only attaches to `mdp_json_config`; the deprecated legacy fields stay
plain textareas.

## Recent Changes

- **Refactored onto the shared `widget-profile-org` base-plugin component** (previously inline `editOrganizationProfile` call, predating the component). Added open-ended `mdp_json_config` passthrough with exclusive precedence over the legacy fields config. `hide_alternate_name_field` now flows through `widget_config['hiddenFields']` instead of a direct `hiddenFields` widget-init arg.
- **`mdp_json_sections` confirmed inert** — the org profile component never had a `sections` arg, so this field has never had any effect (not a regression from this refactor). Relabeled "(Deprecated, Inactive)" with instructions saying so plainly; excluded from the migrate-link and auto-hide behavior that applies to `mdp_json_fields` (see `assets/js/wicket-acc-acf-field-deprecation.js`, `isInertOrgSectionsField()`), so it stays visible rather than disappearing or offering a migrate action that wouldn't do anything useful.
- `mdp_json_config` moved to the top of the field group; the legacy `mdp_json_fields` field relabeled "(Deprecated)", kept plain text/textarea (no CodeMirror), with a "Copy this value into MDP Widget Config" link.
- Added CodeMirror JSON editor UI to the `mdp_json_config` textarea in the block editor.

## Related Documentation

- [Base Block](base-block.md)
- [Organization Search Select Block](ac-org-search-select.md) — picker used when the user has more than one org.
- [Org Logo Block](ac-org-logo.md) — companion block for the logo.