---
title: "AC Individual Profile Block"
audience: [developer, agent, implementer]
php_class: WicketAcc\Blocks\IndividualProfile\init
source_files: ["includes/blocks/ac-individual-profile/init.php", "includes/blocks/ac-individual-profile/render.php", "includes/blocks/ac-individual-profile/block.json", "includes/acf-json/group_66bd0f5a9fca8.json"]
---

# AC Individual Profile Block

## Overview

The Individual Profile block renders a single person's profile inside an account area. It pulls data through the `widget-profile-individual` widget and exposes two MDP-driven ACF fields: `mdp_json_fields` and `mdp_json_sections`.

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

## Recent Changes

- Added `mdp_json_sections` config so editors can declare the section order independently from the field list.

## Related Documentation

- [Base Block](base-block.md)
- [Organization Profile Block](ac-org-profile.md) — same JSON config pattern for org profiles.