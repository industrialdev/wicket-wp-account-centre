---
title: "AC Individual Profile Block"
audience: [developer, agent, implementer]
php_class: WicketAcc\Blocks\IndividualProfile\init
source_files: ["includes/blocks/ac-individual-profile/init.php", "includes/blocks/ac-individual-profile/render.php", "includes/blocks/ac-individual-profile/block.json", "includes/acf-json/group_69a5f1b6ea37e.json", "assets/js/wicket-acc-acf-field-deprecation.js", "src/Assets.php"]
---

# AC Individual Profile Block

## Overview

The Individual Profile block renders a single person's profile inside an account area. It pulls data through the `widget-profile-individual` widget and exposes three MDP-driven ACF fields: `mdp_json_fields`, `mdp_json_sections`, and the newer open-ended `mdp_json_config`.

## Block Architecture

### Directory Structure

```
includes/blocks/ac-individual-profile/
├── block.json         # Block registration
├── init.php           # WicketAcc\Blocks\IndividualProfile\init
└── render.php         # Template renderer (when not using widget)
```

Extends `WicketAcc\Blocks` (see [base-block.md](base-block.md)).

## Core Functionality

### MDP JSON Fields and Sections

The block reads two ACF fields whose values are JSON strings:

- `mdp_json_fields` — flat list of person field keys to render. Decoded and passed to the widget as `fields`.
- `mdp_json_sections` — ordered sections the widget should render. Decoded and passed as `sections`.

Both fields default to `[]` when the ACF value is empty or invalid.

```php
$json_fields   = get_field('mdp_json_fields');
$json_sections = get_field('mdp_json_sections');

$this->mdp_json_fields   = json_decode($json_fields, true)   ?? [];
$this->mdp_json_sections = json_decode($json_sections, true) ?? [];

get_component('widget-profile-individual', [
    'fields'   => $this->mdp_json_fields,
    'sections' => $this->mdp_json_sections,
]);
```

This is the configuration surface that drives what the individual-profile widget renders for a given person.

### MDP Widget Config (open-ended passthrough)

A third ACF field, `mdp_json_config`, is an open-ended JSON object forwarded verbatim to the
widget as `widget_config` (any current or future MDP widget option — `fields`, `sections`,
`resourceLimits`, `resourcePermissions`, etc.) after the base plugin's component strips a small
blocklist of server-owned connection keys. No option-name validation on this plugin's side.

```php
$json_config = get_field('mdp_json_config');
$this->mdp_json_config = json_decode((string) $json_config, true) ?? [];

if (is_array($this->mdp_json_config) && $this->mdp_json_config !== []) {
    get_component('widget-profile-individual', ['widget_config' => $this->mdp_json_config]);
    return;
}

get_component('widget-profile-individual', [
    'fields'   => $this->mdp_json_fields,
    'sections' => $this->mdp_json_sections,
]);
```

**Precedence (exclusive, not merged):** when `mdp_json_config` is non-empty, both legacy
`mdp_json_fields` and `mdp_json_sections` are ignored entirely — a populated legacy value sits
inert. When the config is empty or invalid JSON, the block falls back to the legacy
fields/sections path unchanged.

**Editor UX:** `mdp_json_config` is the top-most field in the ACF group; the legacy
`mdp_json_fields`/`mdp_json_sections` fields sit below it, each with "(Deprecated)" appended to
their label — plain text, no colored notices. Legacy fields stay plain `<textarea>` (no
CodeMirror; see below). Each legacy field gets its own "Copy this value into MDP Widget Config"
link (`assets/js/wicket-acc-acf-field-deprecation.js`, enqueued from `src/Assets.php` only on
block-editor screens) that merges its value into the config field under the matching key
(`fields` or `sections`), preserving whatever the other key already holds — click-triggered only,
never automatic. Nothing persists until the block/post is saved.

**Unsatisfiable configs (accepted risk):** the config is schema-agnostic, so it can express
combinations the MDP widget cannot resolve (e.g. a field marked both required and
hidden/limited/denied). Neither this block nor the underlying component detects or warns about
this beyond JSON-syntax validation. See
[refactor-gf-mdp-widget-config.md](../../../../../wicket-atlas/plans/refactor-gf-mdp-widget-config.md)
for the full plan and risk writeup.

### Editor UI (CodeMirror, config field only)

Only the `mdp_json_config` ACF textarea gets WP core's bundled CodeMirror (line numbers,
JSON syntax coloring, live lint) in the block editor — the deprecated legacy fields
(`mdp_json_fields`, `mdp_json_sections`) intentionally stay plain textareas.
`src/Assets.php::enqueue_admin_assets()` calls `wp_enqueue_code_editor(['type' =>
'application/json'])` gated on `$current_screen->is_block_editor()`, and
`assets/js/wicket-acc-acf-json-editor.js` attaches it via ACF's `ready_field`/`append_field`
lifecycle actions (and detaches on `remove_field`, so it survives block duplication/removal
cleanly). The textarea remains the source of truth; ACF's own value saving is unaffected.
Falls back to a plain textarea when `wp_enqueue_code_editor()` returns `false` (user has
"Disable syntax highlighting when editing code" set in their profile).

## Recent Changes

- Added `mdp_json_sections` config so editors can declare the section order independently from the field list.
- Added open-ended `mdp_json_config` passthrough with exclusive precedence over the legacy fields/sections config. `mdp_json_config` is now the top-most field in the group; legacy fields are labeled "(Deprecated)", stay plain text/textarea (no CodeMirror), and each carries a "Copy this value into MDP Widget Config" link.
- Added CodeMirror JSON editor UI to the `mdp_json_config` textarea in the block editor.

## Related Documentation

- [Base Block](base-block.md)
- [Organization Profile Block](ac-org-profile.md) — same JSON config pattern for org profiles.